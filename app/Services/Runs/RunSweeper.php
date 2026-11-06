<?php

namespace App\Services\Runs;

use App\Jobs\AdvanceWorkflow;
use App\Jobs\RunAgentStep;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\WorkflowExecution;
use Illuminate\Support\Facades\DB;

/**
 * Recovers what a dead worker left behind. Runs whose lease lapsed go back on the queue, from the journal. An effect
 * caught mid-call is marked unknown rather than repeated, because nobody can say whether it happened.
 */
class RunSweeper
{
    /** @return array{resumed: int, unknown: int} */
    public function sweep(): array
    {
        $unknown = AgentEffect::query()
            ->where('status', AgentEffect::EXECUTING)
            ->where('updated_at', '<', now()->subSeconds((int) config('agents.stale_seconds')))
            ->update(['status' => AgentEffect::UNKNOWN, 'error_code' => 'outcome_unknown', 'error_message' => 'The worker stopped during this call, so whether it happened is unknown.']);

        $resumed = 0;

        AgentRun::query()
            ->whereIn('status', [AgentRun::QUEUED, AgentRun::RUNNING])
            ->where(fn ($query) => $query->whereNull('leased_until')->orWhere('leased_until', '<', now()))
            ->where('updated_at', '<', now()->subSeconds((int) config('agents.stale_seconds')))
            ->each(function (AgentRun $run) use (&$resumed) {
                if ($this->alreadyQueued($run)) {
                    return;
                }

                $run->touch();
                RunAgentStep::dispatch($run->id, $run->organization_id);
                $resumed++;
            });

        WorkflowExecution::whereIn('status', WorkflowExecution::OPEN)
            ->where('updated_at', '<', now()->subSeconds((int) config('agents.stale_seconds')))
            ->each(function (WorkflowExecution $execution) {
                $execution->touch();
                AdvanceWorkflow::dispatch($execution->id, $execution->organization_id);
            });

        return compact('resumed', 'unknown');
    }

    private function alreadyQueued(AgentRun $run): bool
    {
        if (config('agents.queue_connection') === 'sync') {
            return false;
        }

        return DB::table((string) config('queue.connections.agents.table'))
            ->where('queue', config('agents.queue'))
            ->where('payload', 'like', '%"runId";i:'.$run->id.';%')
            ->exists();
    }
}
