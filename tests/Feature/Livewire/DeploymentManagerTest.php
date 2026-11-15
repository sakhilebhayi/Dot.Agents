<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Agents\DeploymentManager;
use App\Models\Agent;
use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class DeploymentManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->organization->users()->attach($this->user->id, ['role' => 'admin', 'is_primary' => true]);
        session(['current_organization_id' => $this->organization->id]);

        Gate::before(fn () => true);
    }

    public function test_deployment_manager_renders(): void
    {
        $this->actingAs($this->user);

        Livewire::test(DeploymentManager::class)
            ->assertStatus(200);
    }

    public function test_only_own_org_deployments_are_shown(): void
    {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->create();
        $ownDeployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id]);
        $otherDeployment = AgentDeployment::factory()->create(['organization_id' => $otherOrg->id]);

        Livewire::withQueryParams(['page' => 1])
            ->test(DeploymentManager::class)
            ->assertSee($ownDeployment->name)
            ->assertDontSee($otherDeployment->name);
    }

    public function test_search_filters_deployments_by_name(): void
    {
        $this->actingAs($this->user);

        $alpha = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Alpha Deployment',
        ]);
        $beta = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Beta Deployment',
        ]);

        Livewire::test(DeploymentManager::class)
            ->set('search', 'Alpha')
            ->assertSee('Alpha Deployment')
            ->assertDontSee('Beta Deployment');
    }

    public function test_filter_by_status(): void
    {
        $this->actingAs($this->user);

        AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Active One',
            'status' => 'active',
        ]);
        AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Paused One',
            'status' => 'paused',
        ]);

        Livewire::test(DeploymentManager::class)
            ->set('filterStatus', 'active')
            ->assertSee('Active One')
            ->assertDontSee('Paused One');
    }

    /**
     * Tenant-OR sweep (dot-ui 0.6.1 re-sync). The search matches the deployment's name OR its agent's name; that OR must be one grouped
     * condition, so it can neither step outside the business (the agent catalogue is shared, so another business's deployment of an agent
     * called "Invoice clerk" must not turn up) nor outvote the status filter (it used to: `name like ? or exists(agent) and status = ?`).
     */
    public function test_a_search_never_returns_another_businesss_deployment_even_when_the_agent_name_matches(): void
    {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->create();
        $agent = Agent::factory()->create(['name' => 'Invoice clerk']);
        AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'agent_id' => $agent->id, 'name' => 'Our invoices']);
        AgentDeployment::factory()->create(['organization_id' => $otherOrg->id, 'agent_id' => $agent->id, 'name' => 'Their secret ledger']);

        Livewire::test(DeploymentManager::class)
            ->set('search', 'Invoice clerk')
            ->assertSee('Our invoices')
            ->assertDontSee('Their secret ledger');
    }

    public function test_the_status_filter_still_applies_when_a_search_matches_by_name_or_by_agent(): void
    {
        $this->actingAs($this->user);

        $agent = Agent::factory()->create(['name' => 'Ledger keeper']);
        AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'agent_id' => $agent->id, 'name' => 'Ledger live', 'status' => 'active']);
        AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'agent_id' => $agent->id, 'name' => 'Ledger on hold', 'status' => 'paused']);
        AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ledger by name only', 'status' => 'paused']);

        Livewire::test(DeploymentManager::class)
            ->set('search', 'Ledger')
            ->set('filterStatus', 'active')
            ->assertSee('Ledger live')
            ->assertDontSee('Ledger on hold')
            ->assertDontSee('Ledger by name only');
    }

    public function test_pause_deployment_succeeds(): void
    {
        $this->actingAs($this->user);

        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);

        Livewire::test(DeploymentManager::class)
            ->call('pauseDeployment', $deployment->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agent_deployments', [
            'id' => $deployment->id,
            'status' => 'paused',
        ]);
    }

    public function test_resume_deployment_succeeds(): void
    {
        $this->actingAs($this->user);

        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'paused',
        ]);

        Livewire::test(DeploymentManager::class)
            ->call('resumeDeployment', $deployment->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agent_deployments', [
            'id' => $deployment->id,
            'status' => 'active',
        ]);
    }

    public function test_decommission_deployment_succeeds(): void
    {
        $this->actingAs($this->user);

        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);

        Livewire::test(DeploymentManager::class)
            ->call('decommissionDeployment', $deployment->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agent_deployments', [
            'id' => $deployment->id,
            'status' => 'decommissioned',
        ]);
    }
}
