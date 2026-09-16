<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Marketplace\AgentMarketplace;
use App\Models\Agent;
use App\Models\AgentCategory;
use App\Models\AgentDepartment;
use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class AgentMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        session(['current_organization_id' => $this->organization->id]);
        Cache::flush();
    }

    public function test_marketplace_renders(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentMarketplace::class)
            ->assertStatus(200);
    }

    public function test_search_filters_agents(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentMarketplace::class)
            ->set('search', 'analytics')
            ->assertSet('search', 'analytics');
    }

    public function test_sort_by_can_be_changed(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentMarketplace::class)
            ->set('sortBy', 'rating')
            ->assertSet('sortBy', 'rating');
    }

    public function test_departments_are_cached(): void
    {
        $this->actingAs($this->user);

        AgentDepartment::factory()->create(['is_active' => true, 'sort_order' => 100]);
        AgentDepartment::factory()->create(['is_active' => true, 'sort_order' => 101]);

        Cache::flush(); // clear any stale cache before testing cache fill

        $component = Livewire::actingAs($this->user)
            ->test(AgentMarketplace::class);

        // Departments should contain at least the 2 we just created
        $this->assertGreaterThanOrEqual(2, count($component->get('departments')));

        // Cache should now be populated
        $this->assertTrue(Cache::has('marketplace_departments'));
    }

    public function test_categories_are_cached(): void
    {
        $this->actingAs($this->user);

        AgentCategory::factory()->create(['is_active' => true, 'sort_order' => 100]);

        Cache::flush();

        $component = Livewire::actingAs($this->user)
            ->test(AgentMarketplace::class);

        // At least the factory-created category is returned
        $this->assertGreaterThanOrEqual(1, count($component->get('categories')));

        $this->assertTrue(Cache::has('marketplace_categories'));
    }

    public function test_preview_agent_sets_selected(): void
    {
        $this->actingAs($this->user);

        $agent = Agent::factory()->create(['status' => 'active']);

        $component = Livewire::actingAs($this->user)
            ->test(AgentMarketplace::class)
            ->call('openPreview', $agent->id);

        $this->assertNotNull($component->get('previewAgent'));
        $this->assertSame($agent->id, $component->get('previewAgent')['id']);
    }

    public function test_deploy_dropdown_lists_only_the_current_organizations_own_departments(): void
    {
        $this->actingAs($this->user);

        $ownDepartment = Department::create([
            'organization_id' => $this->organization->id,
            'name' => 'Finance',
            'slug' => 'finance',
            'type' => 'operational',
            'is_active' => true,
        ]);

        $otherOrg = Organization::factory()->create();
        Department::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Legal',
            'slug' => 'legal',
            'type' => 'operational',
            'is_active' => true,
        ]);

        AgentDepartment::factory()->create(['name' => 'Marketing Catalog', 'is_active' => true]);

        $component = Livewire::actingAs($this->user)
            ->test(AgentMarketplace::class);

        $orgDepartments = $component->get('orgDepartments');

        $this->assertCount(1, $orgDepartments);
        $this->assertSame($ownDepartment->id, $orgDepartments->first()->id);
        $this->assertInstanceOf(Department::class, $orgDepartments->first());
    }

    public function test_deploy_persists_the_selected_organization_department(): void
    {
        Event::fake();
        $this->actingAs($this->user);
        $this->organization->users()->attach($this->user->id, [
            'role' => 'owner',
            'is_primary' => true,
            'joined_at' => now(),
        ]);

        $department = Department::create([
            'organization_id' => $this->organization->id,
            'name' => 'Finance',
            'slug' => 'finance',
            'type' => 'operational',
            'is_active' => true,
        ]);

        $agent = Agent::factory()->create(['status' => 'active']);

        $component = Livewire::actingAs($this->user)
            ->test(AgentMarketplace::class)
            ->call('startDeploy', $agent->id)
            ->set('deployForm.department_id', $department->id)
            ->call('deploy');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('agent_deployments', [
            'agent_id' => $agent->id,
            'organization_id' => $this->organization->id,
            'department_id' => $department->id,
        ]);
    }
}
