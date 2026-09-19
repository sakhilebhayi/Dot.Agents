<?php

namespace App\Listeners;

use App\Events\AgentTaskCompleted;
use App\Jobs\GenerateAgentScorecard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateScorecardOnTaskComplete implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 2;

    public function handle(AgentTaskCompleted $event): void
    {
        $task = $event->task;
        $deployment = $task->deployment;

        if (! $deployment) {
            return;
        }

        // Only regenerate weekly scorecards in real-time; monthly runs via schedule
        GenerateAgentScorecard::dispatch(
            $deployment,
            'weekly'
        )->delay(now()->addSeconds(30)); // small delay to batch rapid task completions
    }

    public function failed(AgentTaskCompleted $event, Throwable $exception): void
    {
        Log::error('[UpdateScorecardOnTaskComplete] Failed to dispatch scorecard job', [
            'task_id' => $event->task->id,
            'deployment_id' => $event->task->agent_deployment_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
