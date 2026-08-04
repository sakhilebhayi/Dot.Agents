<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Workflows\WorkflowList;
use App\Models\AgentWorkflow;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowListTest extends TestCase
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

    public function test_workflow_list_renders(): void
    {
        $this->actingAs($this->user);

        Livewire::test(WorkflowList::class)
            ->assertStatus(200);
    }

    public function test_only_own_org_workflows_are_shown(): void
    {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->create();
        $ownWorkflow = AgentWorkflow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'My Workflow',
        ]);
        $otherWorkflow = AgentWorkflow::factory()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Their Workflow',
        ]);

        Livewire::test(WorkflowList::class)
            ->assertSee('My Workflow')
            ->assertDontSee('Their Workflow');
    }

    public function test_create_modal_opens(): void
    {
        $this->actingAs($this->user);

        Livewire::test(WorkflowList::class)
            ->assertSet('showCreateModal', false)
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true);
    }

    public function test_create_workflow_validates_required_fields(): void
    {
        $this->actingAs($this->user);

        Livewire::test(WorkflowList::class)
            ->call('openCreateModal')
            ->set('newName', '')
            ->call('createWorkflow')
            ->assertHasErrors(['newName']);
    }

    public function test_create_workflow_persists_to_database(): void
    {
        $this->actingAs($this->user);

        Livewire::test(WorkflowList::class)
            ->call('openCreateModal')
            ->set('newName', 'Lead Qualification Flow')
            ->set('newDescription', 'Qualifies inbound leads automatically.')
            ->set('newTrigger', 'event')
            ->call('createWorkflow')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agent_workflows', [
            'name' => 'Lead Qualification Flow',
            'organization_id' => $this->organization->id,
            'trigger_type' => 'event',
        ]);
    }

    public function test_delete_workflow_removes_from_database(): void
    {
        $this->actingAs($this->user);

        $workflow = AgentWorkflow::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        Livewire::test(WorkflowList::class)
            ->call('deleteWorkflow', $workflow->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('agent_workflows', ['id' => $workflow->id, 'deleted_at' => null]);
    }

    public function test_delete_workflow_from_other_org_is_rejected(): void
    {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->create();
        $otherWorkflow = AgentWorkflow::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        // deleteWorkflow scopes the query to session org — findOrFail throws
        // ModelNotFoundException for cross-tenant IDs (which becomes 404 in HTTP layer).
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(WorkflowList::class)
            ->call('deleteWorkflow', $otherWorkflow->id);
    }

    /**
     * Regression test for the null-organization-context bug pattern (see
     * Dot.Mines commit 0cc4362): OrganizationContextMiddleware can leave
     * session('current_organization_id') null for a request even though the
     * user is authenticated (e.g. their org membership was revoked between
     * requests). createWorkflow() used to call
     * Organization::findOrFail(session('current_organization_id')) directly,
     * which crashed with ModelNotFoundException instead of a clean 403. It
     * now goes through ResolvesCurrentOrganization::requireCurrentOrganization().
     */
    public function test_create_workflow_with_no_organization_context_aborts_instead_of_crashing(): void
    {
        $this->actingAs($this->user);
        session()->forget('current_organization_id');

        Livewire::test(WorkflowList::class)
            ->set('newName', 'Orphaned Workflow Name')
            ->call('createWorkflow')
            ->assertForbidden();

        $this->assertDatabaseMissing('agent_workflows', ['name' => 'Orphaned Workflow Name']);
    }
}
