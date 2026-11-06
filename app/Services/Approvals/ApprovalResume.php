<?php

namespace App\Services\Approvals;

use App\Jobs\RunAgentStep;
use App\Models\AgentApproval;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Services\Runs\Journal;
use App\Services\Runs\RunKick;
use App\Services\Runs\ToolJournal;

/** Puts a run back to work once a person has answered. Waiting held no process, so this is just the next step. */
class ApprovalResume
{
    public function __construct(
        private readonly Journal $journal,
        private readonly ToolJournal $toolJournal,
        private readonly RunKick $kick,
    ) {}

    /** @param  array<string, mixed>|null  $editedArguments */
    public function approved(AgentApproval $approval, ?array $editedArguments = null): void
    {
        $effect = $this->effect($approval);

        $effect?->update(['status' => AgentEffect::APPROVED, 'arguments' => $editedArguments ?? $effect->arguments]);

        $this->resume($approval, $editedArguments === null ? 'A person approved this' : 'A person changed this and approved it');
    }

    /** The model hears the person's own words, and the action is finished with. */
    public function declined(AgentApproval $approval, string $code, string $message): void
    {
        $effect = $this->effect($approval);

        if ($effect !== null) {
            $effect->update(['status' => AgentEffect::DENIED, 'error_code' => $code, 'error_message' => $message]);
            $this->toolJournal->result($approval->run, $effect, 'A person answered', $message, ['error' => true, 'code' => $code]);
        }

        $this->resume($approval, 'A person answered');
    }

    private function resume(AgentApproval $approval, string $title): void
    {
        $run = $approval->run;

        if ($run === null || $run->isFinished()) {
            return;
        }

        $this->journal->write($run, AgentRunStep::APPROVAL, $title, [
            'latency_ms' => (int) $approval->created_at->diffInMilliseconds(now()),
            'payload' => ['approval' => $approval->uuid],
        ]);

        $run->update(['status' => AgentRun::RUNNING]);

        RunAgentStep::dispatch($run->id, $run->organization_id);
        $this->kick->after($run);
    }

    private function effect(AgentApproval $approval): ?AgentEffect
    {
        return $approval->agent_effect_id === null ? null : AgentEffect::find($approval->agent_effect_id);
    }
}
