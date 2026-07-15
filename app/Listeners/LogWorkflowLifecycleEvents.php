<?php

namespace App\Listeners;

use App\Events\WorkflowSaved;
use App\Events\WorkflowStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogWorkflowLifecycleEvents implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    public function handleWorkflowSaved(WorkflowSaved $event): void
    {
        Log::info('workflow.saved', [
            'organization_id' => $event->workflow->organization_id,
            'workflow_id' => $event->workflow->id,
            'workflow_name' => $event->workflow->name,
            'node_count' => $event->nodeCount,
        ]);
    }

    public function handleWorkflowStatusUpdated(WorkflowStatusUpdated $event): void
    {
        Log::info('workflow.status_updated', [
            'organization_id' => $event->workflow->organization_id,
            'workflow_id' => $event->workflow->id,
            'workflow_name' => $event->workflow->name,
            'status' => $event->status,
        ]);
    }
}
