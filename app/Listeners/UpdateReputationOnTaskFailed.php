<?php

namespace App\Listeners;

use App\Events\AgentTaskFailed;
use App\Jobs\SendPlatformNotification;
use App\Services\AI\AgentReputationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Invalidates the agent reputation cache on task failure so failure_rate
 * is reflected immediately in subsequent reputation computes.
 * Also notifies the requester that their task has failed.
 */
class UpdateReputationOnTaskFailed implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'agents';

    public int $tries = 3;

    public function __construct(
        private readonly AgentReputationService $reputationService,
    ) {}

    public function handle(AgentTaskFailed $event): void
    {
        $task = $event->task;
        $deployment = $task->deployment;

        if (! $deployment) {
            return;
        }

        // Invalidate cached reputation to reflect increased failure rate
        $this->reputationService->invalidate(
            deploymentId: $deployment->id,
            organizationId: $task->organization_id,
        );

        // Notify the requester of the failure
        if ($task->assigned_by) {
            SendPlatformNotification::dispatch(
                userId: $task->assigned_by,
                organizationId: $task->organization_id,
                type: 'task_failed',
                title: "Task Failed: {$deployment->display_name}",
                message: $event->reason ?: 'The agent task did not complete successfully. Please try again or contact support.',
                severity: 'warning',
                data: [
                    'task_id' => $task->id,
                    'deployment_id' => $deployment->id,
                    'reason' => $event->reason,
                ],
                actionUrl: "/agents/{$deployment->id}"
            );
        }
    }

    public function failed(AgentTaskFailed $event, Throwable $exception): void
    {
        Log::warning('[UpdateReputationOnTaskFailed] Failed', [
            'task_id' => $event->task->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
