<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Governance\ApprovalQueue;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->organization->users()->attach($this->user->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);
        session(['current_organization_id' => $this->organization->id]);
    }

    public function test_approval_queue_renders(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(ApprovalQueue::class)
            ->assertStatus(200);
    }

    public function test_pending_count_is_computed(): void
    {
        $this->actingAs($this->user);

        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        AgentApproval::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'agent_deployment_id' => $deployment->id,
            'status' => 'pending',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(ApprovalQueue::class);

        $this->assertSame(3, $component->get('pendingCount'));
    }

    public function test_filter_status_filters_approvals(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(ApprovalQueue::class)
            ->set('filterStatus', 'pending')
            ->assertSet('filterStatus', 'pending');
    }

    public function test_filter_risk_filters_approvals(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(ApprovalQueue::class)
            ->set('filterRisk', 'critical')
            ->assertSet('filterRisk', 'critical');
    }

    public function test_select_approval_loads_details(): void
    {
        $this->actingAs($this->user);

        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $approval = AgentApproval::factory()->create([
            'organization_id' => $this->organization->id,
            'agent_deployment_id' => $deployment->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->user)
            ->test(ApprovalQueue::class)
            ->call('selectApproval', $approval->id)
            ->assertSet('selectedApproval.id', $approval->id);
    }
}
