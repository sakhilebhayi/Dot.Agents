<?php

namespace App\Actions\Agents;

use App\Events\AgentResumed;
use App\Models\AgentDeployment;
use App\Services\Governance\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class ResumeDeploymentAction
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Resume a paused AgentDeployment.
     *
     * Sets deployment status back to 'active' and fires the AgentResumed
     * domain event so analytics and scheduling listeners can re-activate.
     *
     * @param  AgentDeployment  $deployment  The deployment to resume.
     * @return AgentDeployment The refreshed deployment with 'active' status.
     *
     * @throws AuthorizationException When actor lacks 'update' permission.
     */
    public function execute(AgentDeployment $deployment): AgentDeployment
    {
        Gate::authorize('update', $deployment);

        $deployment->update(['status' => 'active']);

        $this->auditService->logUserAction(
            event: 'deployment.resumed',
            description: "Deployment {$deployment->display_name} resumed",
            subject: $deployment,
        );

        event(new AgentResumed($deployment));

        return $deployment->refresh();
    }
}
