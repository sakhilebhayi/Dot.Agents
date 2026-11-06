<?php

namespace App\Services\Approvals;

use App\DTOs\Governance\ProcessApprovalData;
use App\Events\ApprovalProcessed;
use App\Models\AgentApproval;
use App\Models\DecisionLog;
use App\Models\User;
use App\Services\Governance\AuditService;
use App\Services\Tools\ToolArguments;
use App\Services\Tools\ToolCatalogue;
use App\Support\OrgRoles;
use Illuminate\Validation\ValidationException;

/**
 * A person's answer to an approval a run is waiting on. Only a signed-in person gets here: no tool or model can reach
 * this class, so an agent can never approve its own action.
 */
class ApprovalDecision
{
    public function __construct(
        private readonly ApprovalDesk $desk,
        private readonly ApprovalResume $resume,
        private readonly ToolCatalogue $catalogue,
        private readonly ToolArguments $arguments,
        private readonly AuditService $audit,
    ) {}

    public function apply(AgentApproval $approval, User $actor, ProcessApprovalData $data): AgentApproval
    {
        $this->assertNotOwnRequest($approval, $actor);

        if ($data->decision === 'escalated') {
            return $this->escalate($approval, $actor, $data->reviewerNotes);
        }

        $edited = $data->decision === 'approved' ? $this->edited($approval, $data->editedArguments) : null;

        if (! $this->claim($approval, $actor, $data, $edited)) {
            throw ValidationException::withMessages(['approval' => 'Someone else has already answered this.']);
        }

        $this->desk->record($approval, $actor, $edited === null ? $data->decision : 'approved_with_changes', $data->reviewerNotes);
        $this->audit->logUserAction(event: "approval.{$data->decision}", description: "Approval {$approval->uuid}: {$data->decision}", subject: $approval, metadata: ['notes' => $data->reviewerNotes, 'edited' => $edited !== null]);

        $this->log($approval, $actor, $data);

        match ($data->decision) {
            'approved' => $this->resume->approved($approval, $edited),
            'rejected' => $this->resume->declined($approval, 'rejected', 'A person said no'.$this->because($data->reviewerNotes)),
            default => $this->resume->declined($approval, 'changes_requested', 'A person asked for changes'.$this->because($data->reviewerNotes)),
        };

        ApprovalProcessed::dispatch($approval->refresh());

        return $approval;
    }

    /** The decision log holds what people decided about agent work, in plain words. */
    private function log(AgentApproval $approval, User $actor, ProcessApprovalData $data): void
    {
        DecisionLog::create([
            'organization_id' => $approval->organization_id,
            'agent_deployment_id' => $approval->agent_deployment_id,
            'decision_type' => 'approval',
            'title' => $approval->title,
            'decision_summary' => ucfirst(str_replace('_', ' ', $data->decision)),
            'reasoning' => (string) $data->reviewerNotes,
            'human_reviewed' => true,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Only one answer can win, however many people press a button at once.
     *
     * @param  array<string, mixed>|null  $edited
     */
    private function claim(AgentApproval $approval, User $actor, ProcessApprovalData $data, ?array $edited): bool
    {
        $hash = $edited === null ? $approval->action_hash : ActionHash::of($approval->proposed_action['tool'], (int) $approval->proposed_action['version'], $edited);

        return AgentApproval::whereKey($approval->id)->where('status', 'pending')->update([
            'status' => $data->decision,
            'reviewer_notes' => $data->reviewerNotes,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'action_hash' => $hash,
        ]) === 1;
    }

    /** The person who set high-risk work going does not sign it off, unless nobody else in the business can. */
    private function assertNotOwnRequest(AgentApproval $approval, User $actor): void
    {
        if (! in_array($approval->risk_level, ['high', 'critical'], true) || $approval->run?->initiated_by !== $actor->id) {
            return;
        }

        $someoneElse = $approval->organization->users()->whereKeyNot($actor->id)->get()
            ->contains(fn (User $user) => $user->canInOrganization('approvals.review', $approval->organization_id));

        if ($someoneElse) {
            throw ValidationException::withMessages(['approval' => 'You started this work, so someone else needs to approve it.']);
        }
    }

    /**
     * @param  array<string, mixed>|null  $edited
     * @return array<string, mixed>|null
     */
    private function edited(AgentApproval $approval, ?array $edited): ?array
    {
        if ($edited === null) {
            return null;
        }

        // A form hands everything back as text. Numbers go back to being numbers before anything compares them.
        $original = $approval->proposed_action['values'];
        $edited = collect($edited)->map(fn ($value, $key) => is_int($original[$key] ?? null) && is_numeric($value) ? (int) $value : $value)->all();

        if ($edited == $original) {
            return null;
        }

        $tool = $this->catalogue->find($approval->organization_id, $approval->proposed_action['tool']);
        $errors = $tool === null ? ['That tool no longer exists.'] : $this->arguments->errors($tool->definition()->schema, $edited);

        if ($errors !== []) {
            throw ValidationException::withMessages(['values' => implode(' ', $errors)]);
        }

        return $edited;
    }

    private function escalate(AgentApproval $approval, User $actor, ?string $note): AgentApproval
    {
        $owner = $approval->organization->users()->wherePivot('role', OrgRoles::OWNER)->first();

        $approval->update(['requested_from' => $owner?->id, 'risk_level' => $approval->risk_level === 'critical' ? 'critical' : 'high']);
        $this->desk->record($approval, $actor, 'escalated', $note);

        return $approval;
    }

    private function because(?string $notes): string
    {
        return filled($notes) ? ": {$notes}" : '.';
    }
}
