<?php

namespace Tests\Feature\Security;

use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private Organization $orgA;

    private Organization $orgB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();
        $this->orgA = Organization::factory()->create(['owner_id' => $this->userA->id]);
        $this->orgB = Organization::factory()->create(['owner_id' => $this->userB->id]);
    }

    public function test_agent_deployments_scoped_to_organization(): void
    {
        $depA = AgentDeployment::factory()->create(['organization_id' => $this->orgA->id]);
        $depB = AgentDeployment::factory()->create(['organization_id' => $this->orgB->id]);

        $orgADeployments = AgentDeployment::where('organization_id', $this->orgA->id)->pluck('id');

        $this->assertContains($depA->id, $orgADeployments);
        $this->assertNotContains($depB->id, $orgADeployments);
    }

    public function test_tasks_scoped_to_organization(): void
    {
        $depA = AgentDeployment::factory()->create(['organization_id' => $this->orgA->id]);
        $depB = AgentDeployment::factory()->create(['organization_id' => $this->orgB->id]);
        $taskA = AgentTask::factory()->create([
            'agent_deployment_id' => $depA->id,
            'organization_id' => $this->orgA->id,
        ]);
        $taskB = AgentTask::factory()->create([
            'agent_deployment_id' => $depB->id,
            'organization_id' => $this->orgB->id,
        ]);

        $orgATasks = AgentTask::where('organization_id', $this->orgA->id)->pluck('id');

        $this->assertContains($taskA->id, $orgATasks);
        $this->assertNotContains($taskB->id, $orgATasks);
    }

    public function test_org_a_cannot_access_org_b_deployment_via_route(): void
    {
        $depB = AgentDeployment::factory()->create(['organization_id' => $this->orgB->id]);

        $this->actingAs($this->userA);

        // Without session org context set to orgA, the route should not expose orgB's data
        $response = $this->get("/agents/{$depB->id}");

        // Either 403 or redirect - not 200 with the data
        $this->assertNotEquals(200, $response->status());
    }

    public function test_organizations_are_independent_entities(): void
    {
        $this->assertNotEquals($this->orgA->id, $this->orgB->id);
        $this->assertEquals($this->userA->id, $this->orgA->owner_id);
        $this->assertEquals($this->userB->id, $this->orgB->owner_id);
    }

    public function test_scope_alone_blocks_cross_organization_access_even_without_an_explicit_where_or_policy_check(): void
    {
        $deployment = AgentDeployment::factory()->create(['organization_id' => $this->orgA->id]);

        session(['current_organization_id' => $this->orgB->id]);

        $this->assertNull(AgentDeployment::find($deployment->id));
        $this->assertSame(0, AgentDeployment::query()->count());

        session(['current_organization_id' => $this->orgA->id]);

        $this->assertNotNull(AgentDeployment::find($deployment->id));
        $this->assertSame(1, AgentDeployment::query()->count());
    }
}
