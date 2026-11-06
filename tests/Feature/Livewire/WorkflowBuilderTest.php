<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Workflows\WorkflowBuilder;
use App\Models\AgentWorkflow;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private AgentWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->workflow = AgentWorkflow::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->user);
        Gate::before(fn () => true);
        session(['current_organization_id' => $this->organization->id]);
    }

    public function test_save_persists_graph_state_and_flashes_success(): void
    {
        $nodes = [['id' => 'n1', 'type' => 'agent', 'label' => 'Research Agent', 'agent_key' => 'task-decomposition', 'config' => []]];
        $connections = [];

        Livewire::test(WorkflowBuilder::class, ['workflowId' => $this->workflow->id])
            ->set('nodes', $nodes)
            ->set('connections', $connections)
            ->call('save')
            ->assertSet('flashType', 'success')
            ->assertSet('flashMessage', 'Saved.');

        $this->assertDatabaseHas('agent_workflows', [
            'id' => $this->workflow->id,
        ]);
    }

    public function test_publish_with_empty_nodes_shows_warning(): void
    {
        Livewire::test(WorkflowBuilder::class, ['workflowId' => $this->workflow->id])
            ->set('nodes', [])
            ->call('publish')
            ->assertSet('flashType', 'warning')
            ->assertSet('flashMessage', 'Add at least one step before publishing.');
    }

    public function test_publish_with_valid_nodes_sets_workflow_to_active(): void
    {
        $nodes = [['id' => 'n1', 'type' => 'agent', 'label' => 'Writer Agent', 'agent_key' => 'task-decomposition', 'config' => []]];

        Livewire::test(WorkflowBuilder::class, ['workflowId' => $this->workflow->id])
            ->set('nodes', $nodes)
            ->call('publish')
            ->assertSet('flashType', 'success');

        $this->assertDatabaseHas('agent_workflows', [
            'id' => $this->workflow->id,
            'status' => 'active',
        ]);
    }
}
