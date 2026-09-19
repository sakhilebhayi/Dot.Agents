<?php

namespace App\Listeners;

use App\Events\AgentTaskCompleted;
use App\Jobs\SendPlatformNotification;
use App\Services\AI\AgentReputationService;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Invalidates the agent reputation cache after a successful task so the next
 * compute() call reflects the latest success rate, confidence, and satisfaction.
 * Also notifies the task requester that their request has been fulfilled.
 */
class UpdateReputationOnTaskComplete implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'agents';

    public int $tries = 3;

    public function __construct(
        private readonly AgentReputationService $reputationService,
        private readonly AuditService $auditService,
    ) {}

    public function handle(AgentTaskCompleted $event): void
    {
        $task = $event->task;
        $deployment = $task->deployment;

        if (! $deployment) {
            return;
        }

        // Invalidate cached reputation so next request recomputes fresh
        $this->reputationService->invalidate(
            deploymentId: $deployment->id,
            organizationId: $task->organization_id,
        );

        // Audit the completion
        $this->auditService->logAgentAction($deployment, 'agent_task.completed', [
            'task_id' => $task->id,
            'confidence_score' => $task->confidence_score,
            'cost' => $task->cost,
        ]);

        // Notify the requester if they're a real user (not a system trigger)
        if ($task->assigned_by) {
            SendPlatformNotification::dispatch(
                userId: $task->assigned_by,
                organizationId: $task->organization_id,
                type: 'task_completed',
                title: "Task Completed by {$deployment->display_name}",
                message: $task->result_summary ? 'Your task has been completed. View the result.' : 'Task completed successfully.',
                severity: 'info',
                data: ['task_id' => $task->id, 'deployment_id' => $deployment->id],
                actionUrl: "/agents/{$deployment->id}"
            );
        }
    }

    public function failed(AgentTaskCompleted $event, Throwable $exception): void
    {
        Log::warning('[UpdateReputationOnTaskComplete] Failed', [
            'task_id' => $event->task->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
