<?php

namespace App\Services\Approvals;

use App\Events\ApprovalRequested;
use App\Models\AgentApproval;
use App\Models\AgentApprovalEvent;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\ApprovalRule;
use App\Models\User;
use App\Services\Tools\GateDecision;
use App\Services\Tools\ToolDefinition;
use App\Support\OrgRoles;
use Throwable;

/** Opens the approval a stopped run is waiting on, and works out who should see it. */
class ApprovalDesk
{
    private const EXPIRY_HOURS = 72;

    public function __construct(private readonly ApprovalCard $card) {}

    public function open(AgentRun $run, AgentEffect $effect, ToolDefinition $tool, GateDecision $decision): AgentApproval
    {
        $rule = $this->rule($run->organization_id, $tool);
        $card = $this->card->build($run, $effect, $tool, $decision);

        $approval = AgentApproval::create([
            'agent_run_id' => $run->id,
            'agent_effect_id' => $effect->id,
            'agent_deployment_id' => $run->agent_deployment_id,
            'organization_id' => $run->organization_id,
            'requested_from' => $this->reviewer($run, $rule?->approver_role)?->id,
            'approval_type' => $tool->category,
            'title' => $card['what'],
            'description' => $card['consequence'],
            'proposed_action' => $card,
            'risk_level' => $decision->code === 'trifecta' && $tool->ceiling === null ? 'high' : $this->card->risk($tool),
            'gate_code' => $decision->code,
            'action_hash' => ActionHash::of($effect->tool_key, $tool->version, $effect->arguments),
            'status' => 'pending',
            'expires_at' => now()->addHours($rule->expires_hours ?? self::EXPIRY_HOURS),
        ]);

        $this->record($approval, null, 'requested', $decision->reason);

        // The record above is what counts. A mail server being down cannot be allowed to fail the run.
        try {
            ApprovalRequested::dispatch($approval);
        } catch (Throwable $e) {
            report($e);
        }

        return $approval;
    }

    /** An effect with an approval behind it runs only as approved. A reviewer's change is approved by being made. */
    public function matches(AgentEffect $effect, ToolDefinition $tool): bool
    {
        $approval = AgentApproval::where('agent_effect_id', $effect->id)->where('status', 'approved')->latest('id')->first();

        return $approval === null || hash_equals((string) $approval->action_hash, ActionHash::of($effect->tool_key, $tool->version, $effect->arguments));
    }

    /** A rule of the business's own that covers this tool, if there is one. */
    public function rule(int $organizationId, ToolDefinition $tool): ?ApprovalRule
    {
        return ApprovalRule::where('organization_id', $organizationId)->where('is_active', true)
            ->where(fn ($query) => $query->where('tool_key', $tool->key)->orWhere('category', $tool->category))
            ->orderBy('id')->first();
    }

    public function record(AgentApproval $approval, ?User $actor, string $action, ?string $note = null): void
    {
        AgentApprovalEvent::create([
            'organization_id' => $approval->organization_id,
            'agent_approval_id' => $approval->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'note' => $note,
        ]);
    }

    private function reviewer(AgentRun $run, ?string $role): ?User
    {
        $people = $run->deployment->organization->users()->get();

        if ($role !== null && ($match = $people->first(fn (User $user) => $user->getRelationValue('pivot')?->role === $role))) {
            return $match;
        }

        $owner = $run->deployment->owner;

        if ($owner !== null && $owner->canInOrganization('approvals.review', $run->organization_id)) {
            return $owner;
        }

        return $people->first(fn (User $user) => $user->getRelationValue('pivot')?->role === OrgRoles::OWNER);
    }
}
