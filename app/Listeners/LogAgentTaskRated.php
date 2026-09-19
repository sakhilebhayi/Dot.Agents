<?php

namespace App\Listeners;

use App\Events\AgentTaskRated;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogAgentTaskRated implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function handle(AgentTaskRated $event): void
    {
        $task = $event->task;
        $deployment = $task->deployment;

        if (! $deployment) {
            return;
        }

        $this->auditService->logAgentAction(
            deployment: $deployment,
            event: 'task.rated',
            data: [
                'task_id' => $task->id,
                'rating' => $event->rating,
                'has_feedback' => ! empty($event->feedback),
            ],
        );
    }

    public function failed(AgentTaskRated $event, Throwable $exception): void
    {
        Log::warning('[LogAgentTaskRated] Failed to log task rating', [
            'task_id' => $event->task->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
