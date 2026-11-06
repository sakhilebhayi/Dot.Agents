<?php

namespace App\Services\Workflows;

use App\Models\AgentRun;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use App\Services\Triggers\BusinessEvents;
use Illuminate\Support\Collection;

/**
 * Says truthfully where an execution stands. It is complete only when every step on the path taken worked. One finished
 * agent is not a finished workflow.
 */
class WorkflowSettlement
{
    public function __construct(private readonly BusinessEvents $events) {}

    /**
     * Brings a step up to date with its run.
     *
     * @param  array<string, mixed>  $node
     */
    public function collect(WorkflowStep $step, array $node): bool
    {
        $run = $step->agent_run_id === null ? null : AgentRun::find($step->agent_run_id);

        if ($run === null) {
            return false;
        }

        if ($run->status === AgentRun::WAITING_APPROVAL && $step->status !== WorkflowStep::WAITING) {
            $step->update(['status' => WorkflowStep::WAITING]);

            return false;
        }

        if (in_array($run->status, [AgentRun::QUEUED, AgentRun::RUNNING], true) && $step->status === WorkflowStep::WAITING) {
            $step->update(['status' => WorkflowStep::RUNNING]);
        }

        if (! $run->isFinished()) {
            return false;
        }

        if ($run->status !== AgentRun::COMPLETED) {
            return $this->failed($step, $node, (string) ($run->error_message ?: "The run ended as {$run->status}."));
        }

        $problem = ResultCheck::problem($node['config']['expect'] ?? null, (string) $run->output);

        if ($problem !== null) {
            return $this->failed($step, $node, $problem);
        }

        $step->update(['status' => WorkflowStep::COMPLETED, 'output' => $run->output, 'finished_at' => now()]);

        return true;
    }

    /** @param  Collection<string, WorkflowStep>  $steps */
    public function halted(Collection $steps, WorkflowGraph $graph): bool
    {
        $stopped = $steps->first(fn (WorkflowStep $step) => $this->stops($step, $graph));

        if ($stopped === null) {
            return false;
        }

        foreach ($steps->where('status', WorkflowStep::PENDING) as $step) {
            $step->update(['status' => WorkflowStep::SKIPPED, 'finished_at' => now()]);
        }

        return true;
    }

    public function settle(WorkflowExecution $execution, WorkflowGraph $graph): void
    {
        $steps = $execution->steps()->get();
        $cost = AgentRun::whereIn('id', $steps->pluck('agent_run_id')->filter())->sum('cost');

        $execution->update(['total_cost' => $cost, 'current_step' => $steps->whereIn('status', [WorkflowStep::COMPLETED, WorkflowStep::FAILED, WorkflowStep::SKIPPED])->count()]);

        if ($steps->whereIn('status', WorkflowStep::OPEN)->isNotEmpty() || $steps->where('status', WorkflowStep::PENDING)->isNotEmpty()) {
            $execution->update(['status' => $steps->where('status', WorkflowStep::WAITING)->isNotEmpty() ? 'waiting_approval' : 'running']);

            return;
        }

        $stopped = $steps->first(fn (WorkflowStep $step) => $this->stops($step, $graph));
        $failed = $steps->where('status', WorkflowStep::FAILED);
        $ends = $steps->filter(fn (WorkflowStep $step) => $graph->outOf($step->node_uuid) === []);

        [$status, $message] = match (true) {
            $stopped !== null => ['failed', "Stopped at \"{$stopped->label}\": {$stopped->error_message}"],
            $failed->isNotEmpty() => ['partial', 'Finished, but these steps failed: '.$failed->pluck('label')->implode(', ').'.'],
            $ends->where('status', WorkflowStep::COMPLETED)->isEmpty() => ['failed', 'No path reached the end of this workflow.'],
            default => ['completed', null],
        };

        $execution->update([
            'status' => $status,
            'error_message' => $message,
            'output_data' => ['results' => $ends->where('status', WorkflowStep::COMPLETED)->map(fn (WorkflowStep $step) => ['step' => $step->label, 'result' => $step->output])->values()->all()],
            'completed_at' => now(),
        ]);

        $this->events->raise($execution->organization_id, 'workflow.finished', $execution->uuid, "{$execution->workflow?->name} ended as {$status}.");
    }

    /** @param  array<string, mixed>  $node */
    private function failed(WorkflowStep $step, array $node, string $message): bool
    {
        $retries = (int) ($node['config']['retries'] ?? 0);

        if ($step->attempts <= $retries) {
            $step->update(['status' => WorkflowStep::PENDING, 'agent_run_id' => null, 'error_message' => $message]);

            return true;
        }

        $step->update(['status' => WorkflowStep::FAILED, 'error_message' => $message, 'finished_at' => now()]);

        return true;
    }

    /** A failed step stops the work unless the step says to carry on, or a line leads out of the failure. */
    private function stops(WorkflowStep $step, WorkflowGraph $graph): bool
    {
        if ($step->status !== WorkflowStep::FAILED) {
            return false;
        }

        $continues = ($graph->nodes[$step->node_uuid]['config']['on_failure'] ?? 'stop') === 'continue';

        return ! $continues && ! collect($graph->outOf($step->node_uuid))->contains(fn ($edge) => $this->isFailureLine($edge['condition']));
    }

    private function isFailureLine(mixed $condition): bool
    {
        $condition = is_string($condition) ? json_decode($condition, true) : $condition;

        return is_array($condition) && ($condition['op'] ?? null) === 'failed';
    }
}
