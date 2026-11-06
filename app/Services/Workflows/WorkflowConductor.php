<?php

namespace App\Services\Workflows;

use App\Jobs\AdvanceWorkflow;
use App\Models\AgentRun;
use App\Models\AgentWorkflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use App\Services\Governance\AuditService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Moves an execution along. Nothing is held in memory: every pass reads the steps, starts whatever is ready, and stops.
 * Each finished run brings it back, and the sweeper does if one never does.
 */
class WorkflowConductor
{
    private const PER_MINUTE = 30;

    public function __construct(private readonly StepStarter $starter, private readonly WorkflowSettlement $settlement, private readonly AuditService $audit) {}

    public function start(AgentWorkflow $workflow, string $request, ?int $by, bool $isTest = false, ?string $idempotencyKey = null): WorkflowExecution
    {
        if ($idempotencyKey !== null && ($existing = $this->existing($workflow, $idempotencyKey))) {
            return $existing;
        }

        abort_if(RateLimiter::tooManyAttempts("workflow-runs:{$workflow->organization_id}", self::PER_MINUTE), 429, 'Too many workflow runs at once.');
        RateLimiter::hit("workflow-runs:{$workflow->organization_id}", 60);

        $graph = WorkflowGraph::of($workflow);

        try {
            $execution = WorkflowExecution::create([
                'uuid' => (string) Str::uuid(),
                'workflow_id' => $workflow->id,
                'organization_id' => $workflow->organization_id,
                'triggered_by' => $by,
                'status' => 'running',
                'input_data' => ['text' => $request],
                'step_results' => [],
                'graph' => json_encode($graph->store()),
                'total_steps' => count($graph->nodes),
                'is_test' => $isTest,
                'idempotency_key' => $idempotencyKey,
                'started_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->existing($workflow, (string) $idempotencyKey);
        }

        foreach ($graph->nodes as $uuid => $node) {
            WorkflowStep::create(['organization_id' => $workflow->organization_id, 'workflow_execution_id' => $execution->id, 'node_uuid' => $uuid, 'label' => $node['label'], 'kind' => $node['kind']]);
        }

        $this->audit->logUserAction(event: 'workflow.started', description: "Started the workflow {$workflow->name}", subject: $workflow, metadata: ['execution' => $execution->uuid]);

        $this->advance($execution->id);

        return $execution->fresh();
    }

    public function advance(int $executionId): void
    {
        $until = now()->addSeconds(30);
        $taken = WorkflowExecution::whereKey($executionId)
            ->where(fn ($query) => $query->whereNull('advancing_until')->orWhere('advancing_until', '<', now()))
            ->whereIn('status', WorkflowExecution::OPEN)
            ->update(['advancing_until' => $until]);

        if ($taken === 0) {
            return;
        }

        $execution = WorkflowExecution::findOrFail($executionId);
        $graph = WorkflowGraph::restore(json_decode($execution->graph, true));

        do {
            $moved = $this->pass($execution, $graph);
        } while ($moved);

        $this->settlement->settle($execution, $graph);

        WorkflowExecution::whereKey($executionId)->update(['advancing_until' => null]);
    }

    public function cancel(WorkflowExecution $execution): void
    {
        $steps = $execution->steps()->get();

        AgentRun::whereIn('id', $steps->pluck('agent_run_id')->filter())->whereIn('status', AgentRun::OPEN)->update(['cancel_requested_at' => now()]);
        $execution->steps()->whereIn('status', [WorkflowStep::PENDING, ...WorkflowStep::OPEN])->update(['status' => WorkflowStep::CANCELLED, 'finished_at' => now()]);
        $execution->update(['status' => 'cancelled', 'error_message' => 'Cancelled by a person.', 'completed_at' => now()]);
    }

    /** Puts a failed step back. Steps that finished keep their results, so nothing done is done twice. */
    public function retry(WorkflowExecution $execution, WorkflowStep $step): void
    {
        $execution->steps()->whereIn('status', [WorkflowStep::SKIPPED, WorkflowStep::CANCELLED])->update(['status' => WorkflowStep::PENDING, 'finished_at' => null]);
        $step->update(['status' => WorkflowStep::PENDING, 'agent_run_id' => null, 'error_message' => null, 'finished_at' => null]);
        $execution->update(['status' => 'running', 'error_message' => null, 'completed_at' => null]);

        AdvanceWorkflow::dispatch($execution->id, $execution->organization_id);
    }

    private function pass(WorkflowExecution $execution, WorkflowGraph $graph): bool
    {
        $steps = $execution->steps()->with('run')->get()->keyBy('node_uuid');
        $moved = false;

        foreach ($steps->whereIn('status', WorkflowStep::OPEN) as $step) {
            $moved = $this->settlement->collect($step, $graph->nodes[$step->node_uuid]) || $moved;
        }

        if ($this->settlement->halted($steps, $graph)) {
            return $moved;
        }

        foreach ($graph->ordered() as $uuid) {
            $moved = $this->consider($execution, $graph, $steps[$uuid], $steps) || $moved;
        }

        return $moved;
    }

    /** @param  Collection<string, WorkflowStep>  $steps */
    private function consider(WorkflowExecution $execution, WorkflowGraph $graph, WorkflowStep $step, $steps): bool
    {
        if ($step->status !== WorkflowStep::PENDING) {
            return false;
        }

        $incoming = $graph->into($step->node_uuid);
        $unfinished = collect($incoming)->contains(fn ($edge) => in_array($steps[$edge['from']]->status, [WorkflowStep::PENDING, ...WorkflowStep::OPEN], true));

        if ($unfinished) {
            return false;
        }

        $taken = array_values(array_filter($incoming, fn ($edge) => EdgeCondition::holds($edge['condition'], $steps[$edge['from']])));

        if ($incoming !== [] && $taken === []) {
            $step->update(['status' => WorkflowStep::SKIPPED, 'finished_at' => now()]);

            return true;
        }

        $this->starter->start($execution, $step, $graph->nodes[$step->node_uuid], array_map(fn ($edge) => $steps[$edge['from']], $taken));

        return true;
    }

    private function existing(AgentWorkflow $workflow, string $key): ?WorkflowExecution
    {
        return WorkflowExecution::where('organization_id', $workflow->organization_id)->where('idempotency_key', $key)->first();
    }
}
