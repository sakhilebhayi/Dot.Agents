<?php

namespace App\Services\AI;

use App\Models\AgentWorkflow;
use App\Models\WorkflowExecution;
use App\Services\AI\Workflow\WorkflowStepExecutor;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Workflow Engine Service
 *
 * Executes AgentWorkflows step by step, tracks execution state,
 * handles conditional branching, approval gates, and failures.
 */
class WorkflowEngineService
{
    public function __construct(
        private readonly AgentOrchestrationService $orchestrator,
        private readonly AuditService $auditService,
        private readonly WorkflowStepExecutor $stepExecutor,
    ) {}

    /**
     * Start executing a workflow, returning the execution record.
     */
    public function start(AgentWorkflow $workflow, array $inputData = [], ?int $triggeredBy = null): WorkflowExecution
    {
        $execution = WorkflowExecution::create([
            'uuid' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'organization_id' => $workflow->organization_id,
            'triggered_by' => $triggeredBy,
            'status' => 'running',
            'current_step' => 0,
            'input_data' => $inputData,
            'step_results' => [],
            'started_at' => now(),
        ]);

        $this->auditService->logUserAction(
            event: 'workflow.started',
            description: "Workflow '{$workflow->name}' execution started",
            subject: $workflow,
            metadata: ['execution_id' => $execution->id]
        );

        try {
            $this->runSteps($workflow, $execution);
        } catch (Throwable $e) {
            $this->failExecution($execution, $e->getMessage());
            Log::error('[WorkflowEngine] Execution failed', [
                'workflow_id' => $workflow->id,
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $execution->refresh();
    }

    /**
     * Resume a paused workflow execution from the current step.
     */
    public function resume(WorkflowExecution $execution, array $resumeData = []): WorkflowExecution
    {
        if ($execution->status !== 'paused') {
            throw new \RuntimeException("Execution #{$execution->id} is not paused (status: {$execution->status}).");
        }

        $execution->update([
            'status' => 'running',
            'resumed_data' => $resumeData,
        ]);

        $workflow = $execution->workflow;

        if (! $workflow) {
            throw new \RuntimeException("Execution #{$execution->id} has no associated workflow.");
        }

        try {
            $this->runSteps($workflow, $execution);
        } catch (Throwable $e) {
            $this->failExecution($execution, $e->getMessage());
        }

        return $execution->refresh();
    }

    /**
     * Abort a running or paused execution.
     */
    public function abort(WorkflowExecution $execution, string $reason = 'Manually aborted'): void
    {
        $execution->update([
            'status' => 'failed',
            'error_message' => $reason,
            'completed_at' => now(),
        ]);

        $this->auditService->logUserAction(
            event: 'workflow.aborted',
            description: "Workflow execution #{$execution->id} aborted",
            subject: $execution,
            metadata: ['reason' => $reason]
        );
    }

    /**
     * Run all pending steps in order from the current step index.
     */
    private function runSteps(AgentWorkflow $workflow, WorkflowExecution $execution): void
    {
        $steps = $workflow->steps ?? [];
        $currentStep = $execution->current_step ?? 0;
        $stepResults = $execution->step_results ?? [];

        for ($i = $currentStep; $i < count($steps); $i++) {
            $step = $steps[$i];

            // Evaluate conditional logic
            if (! $this->stepExecutor->evaluateCondition($step, $stepResults)) {
                $stepResults[$i] = ['status' => 'skipped', 'reason' => 'condition_not_met'];
                $execution->update(['step_results' => $stepResults, 'current_step' => $i + 1]);

                continue;
            }

            // Handle approval gate steps
            if (($step['type'] ?? '') === 'approval_gate') {
                $execution->update([
                    'status' => 'paused',
                    'current_step' => $i,
                    'step_results' => $stepResults,
                ]);

                return; // pause and wait for manual resume
            }

            // Execute the step
            $result = $this->stepExecutor->executeStep($step, $execution, $stepResults);
            $stepResults[$i] = $result;

            $execution->update([
                'current_step' => $i + 1,
                'step_results' => $stepResults,
            ]);

            // Stop if a step hard-failed
            if ($result['status'] === 'failed' && ($step['halt_on_failure'] ?? true)) {
                $this->failExecution($execution, "Step {$i} failed: ".($result['error'] ?? 'Unknown'));

                return;
            }
        }

        // All steps complete
        $execution->update([
            'status' => 'completed',
            'output_data' => $this->stepExecutor->collectOutputs($stepResults),
            'completed_at' => now(),
        ]);

        $this->auditService->logUserAction(
            event: 'workflow.completed',
            description: "Workflow '{$workflow->name}' completed successfully",
            subject: $workflow,
            metadata: ['execution_id' => $execution->id, 'steps_run' => count($steps)]
        );
    }

    /**
     * Mark an execution as failed and record the error.
     */
    private function failExecution(WorkflowExecution $execution, string $errorMessage): void
    {
        $execution->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);

        $this->auditService->logUserAction(
            event: 'workflow.failed',
            description: "Workflow execution #{$execution->id} failed",
            subject: $execution,
            metadata: ['error' => $errorMessage]
        );
    }
}
