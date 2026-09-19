<?php

namespace App\Jobs;

use App\Events\AgentTaskCompleted;
use App\Events\AgentTaskFailed;
use App\Models\AgentTask;
use App\Services\AI\AgentOrchestrationService;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAgentTask implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 300;

    public function __construct(
        public readonly AgentTask $task
    ) {
        $this->onQueue('agent-tasks');
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping("task-{$this->task->id}")];
    }

    public function handle(AgentOrchestrationService $orchestrator, AuditService $auditService): void
    {
        if ($this->task->status !== 'pending') {
            return;
        }

        try {
            $deployment = $this->task->deployment;

            if (! $deployment) {
                throw new \RuntimeException("Task {$this->task->id} has no associated deployment.");
            }

            $completedTask = $orchestrator->executeTask(
                $deployment,
                $this->task
            );

            $auditService->logAgentAction(
                $deployment,
                'task_executed',
                [
                    'task_id' => $this->task->id,
                    'status' => $completedTask->status,
                    'confidence_score' => $completedTask->confidence_score,
                    'cost' => $completedTask->cost,
                ]
            );

            if ($completedTask->status === 'completed') {
                event(new AgentTaskCompleted($completedTask));
            }
        } catch (Throwable $e) {
            $this->task->update([
                'status' => 'failed',
                'completed_at' => now(),
                'metadata' => array_merge($this->task->metadata ?? [], [
                    'failure_reason' => $e->getMessage(),
                    'failed_at' => now()->toIso8601String(),
                    'attempt' => $this->attempts(),
                ]),
            ]);

            event(new AgentTaskFailed($this->task, $e->getMessage(), $e));

            Log::error('[ProcessAgentTask] Task execution failed', [
                'task_id' => $this->task->id,
                'deployment_id' => $this->task->agent_deployment_id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->task->update(['status' => 'failed', 'completed_at' => now()]);

        Log::critical('[ProcessAgentTask] Job permanently failed', [
            'task_id' => $this->task->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
