<?php

namespace App\Actions\Agents;

use App\Actions\Concerns\LogsActionErrors;
use App\DTOs\Agents\DeployAgentData;
use App\Events\AgentDeployed;
use App\Models\AgentDeployment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DeployAgentAction
{
    use LogsActionErrors;

    /**
     * Deploy an Agent for an Organization.
     *
     * Creates an AgentDeployment record, assigns a UUID, and fires the
     * AgentDeployed domain event so downstream listeners can bootstrap
     * memory, scoring, and governance hooks.
     *
     * @param  DeployAgentData  $data  Typed DTO carrying all deployment configuration.
     * @return AgentDeployment The newly created, persisted deployment.
     *
     * @throws AuthorizationException When actor lacks 'create' permission.
     */
    public function execute(DeployAgentData $data): AgentDeployment
    {
        return $this->runAction(function () use ($data) {
            Gate::authorize('create', [AgentDeployment::class, $data->organizationId]);

            $deployment = AgentDeployment::create([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $data->organizationId,
                'agent_id' => $data->agentId,
                'department_id' => $data->departmentId,
                'deployed_by' => $data->deployedBy,
                'name' => $data->name,
                'deployment_mode' => $data->deploymentMode,
                'confidence_threshold' => $data->confidenceThreshold,
                'custom_instructions' => $data->customInstructions,
                'requires_human_approval' => $data->deploymentMode !== 'autonomous',
                'status' => 'active',
            ]);

            $deployment->agent()->increment('total_deployments');

            event(new AgentDeployed($deployment));

            return $deployment;
        }, context: ['organization_id' => $data->organizationId, 'agent_id' => $data->agentId]);
    }
}
