<?php

declare(strict_types=1);

namespace App\Actions\Workflows;

use App\DTOs\Workflows\UpdateWorkflowStatusData;
use App\Events\WorkflowStatusUpdated;
use App\Models\AgentWorkflow;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class UpdateWorkflowStatusAction
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Publish a workflow — makes it active and ready for execution.
     */
    public function publish(AgentWorkflow $workflow): AgentWorkflow
    {
        Gate::authorize('update', $workflow);

        $data = UpdateWorkflowStatusData::publish();
        $workflow->update(['status' => $data->status]);

        $this->auditService->logUserAction(
            event: 'workflow.published',
            description: "Workflow '{$workflow->name}' published",
            subject: $workflow,
        );

        $refreshed = $workflow->refresh();
        event(new WorkflowStatusUpdated($refreshed, 'active'));

        return $refreshed;
    }

    /**
     * Unpublish a workflow — reverts it to draft for further editing.
     */
    public function unpublish(AgentWorkflow $workflow): AgentWorkflow
    {
        Gate::authorize('update', $workflow);

        $data = UpdateWorkflowStatusData::unpublish();
        $workflow->update(['status' => $data->status]);

        $this->auditService->logUserAction(
            event: 'workflow.unpublished',
            description: "Workflow '{$workflow->name}' reverted to draft",
            subject: $workflow,
        );

        $refreshed = $workflow->refresh();
        event(new WorkflowStatusUpdated($refreshed, 'draft'));

        return $refreshed;
    }
}
