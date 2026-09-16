<?php

namespace Tests\Feature\Policies;

use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentDeploymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_member_can_create_deployment_under_the_plan_limit(): void
    {
        SubscriptionPlan::factory()->create(['slug' => 'starter', 'max_agents' => 3]);
        $org = Organization::factory()->create(['plan' => 'starter']);
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['role' => 'editor']);

        AgentDeployment::factory()->count(2)->create(['organization_id' => $org->id, 'status' => 'active']);

        $this->assertTrue($user->can('create', [AgentDeployment::class, $org->id]));
    }

    public function test_org_member_cannot_create_deployment_once_the_plan_limit_is_reached(): void
    {
        SubscriptionPlan::factory()->create(['slug' => 'starter', 'max_agents' => 3]);
        $org = Organization::factory()->create(['plan' => 'starter']);
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['role' => 'editor']);

        AgentDeployment::factory()->count(3)->create(['organization_id' => $org->id, 'status' => 'active']);

        $this->assertFalse($user->can('create', [AgentDeployment::class, $org->id]));
    }

    /**
     * Regression: -1 is the seeded convention for "unlimited" agents (see
     * database/seeders/AgentPlatformSeeder.php's enterprise plan). The naive
     * `$currentCount < $maxAgents` comparison made this always false — the
     * enterprise plan (the platform's top tier) could never deploy a single
     * agent, even starting from zero, because 0 < -1 is false.
     */
    public function test_org_member_can_create_deployment_when_plan_has_unlimited_agents(): void
    {
        SubscriptionPlan::factory()->create(['slug' => 'enterprise', 'max_agents' => -1]);
        $org = Organization::factory()->create(['plan' => 'enterprise']);
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['role' => 'editor']);

        AgentDeployment::factory()->count(23)->create(['organization_id' => $org->id, 'status' => 'active']);

        $this->assertTrue($user->can('create', [AgentDeployment::class, $org->id]));
    }

    public function test_non_member_cannot_create_deployment(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse($user->can('create', [AgentDeployment::class, $org->id]));
    }
}
