<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentConfigVersion;
use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\User;
use App\Services\Agents\AgentConfig;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgentDeployment>
 */
class AgentDeploymentFactory extends Factory
{
    protected $model = AgentDeployment::class;

    /** A deployed agent always has a published setup, as DeployAgentAction leaves it. */
    public function configure(): static
    {
        return $this->afterCreating(function (AgentDeployment $deployment) {
            $version = AgentConfigVersion::create([
                'organization_id' => $deployment->organization_id,
                'agent_deployment_id' => $deployment->id,
                'number' => 1,
                'state' => AgentConfigVersion::PUBLISHED,
                'config' => AgentConfig::defaults(),
                'published_at' => now(),
            ]);

            $deployment->forceFill(['published_version_id' => $version->id])->saveQuietly();
        });
    }

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'organization_id' => Organization::factory(),
            'agent_id' => Agent::factory(),
            'department_id' => null,
            'team_id' => null,
            'deployed_by' => User::factory(),
            'name' => fake()->words(3, true).' Agent',
            'alias' => null,
            'custom_instructions' => null,
            'deployment_mode' => 'advisory',
            'status' => 'active',
            'requires_human_approval' => true,
            'confidence_threshold' => 75.0,
            'model_override' => null,
            'model_config_override' => null,
            'context_config' => null,
            'enable_memory' => true,
            'enable_long_term_memory' => false,
            'memory_retention_days' => 90,
            'risk_tolerance' => 50.0,
            'allowed_actions' => [],
            'restricted_actions' => [],
            'data_access_scope' => [],
            'custom_kpis' => null,
            'notification_config' => null,
            'integration_config' => null,
            'metadata' => null,
            'deployed_at' => now(),
            'last_active_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function autonomous(): static
    {
        return $this->state([
            'deployment_mode' => 'autonomous',
            'requires_human_approval' => false,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(['organization_id' => $organization->id]);
    }
}
