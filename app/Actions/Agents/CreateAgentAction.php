<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\CreateAgentData;
use App\Models\Agent;
use App\Models\AgentCategory;
use App\Models\AgentConfigVersion;
use App\Models\AgentDepartment;
use App\Models\AgentDeployment;
use App\Models\Department;
use App\Services\Agents\AgentConfig;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** A new agent starts as a draft that belongs to the business. Nothing runs until a version is published. */
class CreateAgentAction
{
    public function __construct(private readonly AuditService $audit) {}

    public function execute(CreateAgentData $data): AgentDeployment
    {
        Gate::authorize('create', [AgentDeployment::class, $data->organizationId]);

        if ($data->departmentId !== null) {
            Department::withoutGlobalScopes()->where('organization_id', $data->organizationId)->findOrFail($data->departmentId);
        }

        $deployment = DB::transaction(function () use ($data) {
            $agent = $data->templateId !== null ? Agent::findOrFail($data->templateId) : $this->privateAgent($data);

            $deployment = AgentDeployment::create([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $data->organizationId,
                'agent_id' => $agent->id,
                'department_id' => $data->departmentId,
                'deployed_by' => $data->createdBy,
                'owner_id' => $data->createdBy,
                'name' => $data->name,
                'role' => $data->role,
                'responsibility' => $data->responsibility,
                'deployment_mode' => 'advisory',
                'status' => 'draft',
                'requires_human_approval' => true,
            ]);

            AgentConfigVersion::create([
                'organization_id' => $data->organizationId,
                'agent_deployment_id' => $deployment->id,
                'number' => 1,
                'state' => AgentConfigVersion::DRAFT,
                'config' => AgentConfig::normalise($data->config + ($agent->template ?? [])),
                'created_by' => $data->createdBy,
            ]);

            return $deployment;
        });

        $this->audit->logUserAction(event: 'agent.created', description: "Created the agent {$data->name}", subject: $deployment);

        return $deployment;
    }

    private function privateAgent(CreateAgentData $data): Agent
    {
        return Agent::create([
            'organization_id' => $data->organizationId,
            'uuid' => (string) Str::uuid(),
            'category_id' => AgentCategory::firstOrCreate(['slug' => 'business-own'], ['name' => 'Your own agents'])->id,
            'department_id' => AgentDepartment::firstOrCreate(['slug' => 'business-own'], ['name' => 'Your own agents'])->id,
            'created_by' => $data->createdBy,
            'name' => $data->name,
            'slug' => Str::slug($data->name).'-'.Str::lower(Str::random(8)),
            'description' => $data->responsibility ?? $data->role,
            'agent_type' => 'custom',
            'status' => 'private',
            'pricing_model' => 'included',
            'default_deployment_mode' => 'advisory',
        ]);
    }
}
