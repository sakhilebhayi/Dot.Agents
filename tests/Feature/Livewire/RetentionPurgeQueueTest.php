<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Governance\RetentionPurgeQueue;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Models\RetentionPurgeProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RetentionPurgeQueueTest extends TestCase
{
    use RefreshDatabase;

    private function eligibleTaskProposal(): RetentionPurgeProposal
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
        ]);

        return RetentionPurgeProposal::create([
            'model_class' => AgentTask::class,
            'eligible_count' => 1,
            'retention_summary' => 'Completed AgentTask rows older than 90 days',
            'status' => 'pending',
        ]);
    }

    private function platformAdmin(): User
    {
        if (! Role::where('name', 'platform_admin')->exists()) {
            Role::create(['name' => 'platform_admin']);
        }

        $org = Organization::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('platform_admin');
        session(['current_organization_id' => $org->id]);

        return $admin;
    }

    public function test_platform_admin_can_approve_via_the_component(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();

        Livewire::actingAs($admin)
            ->test(RetentionPurgeQueue::class)
            ->call('approve', $proposal->id);

        $this->assertSame('approved', $proposal->fresh()->status);
        $this->assertDatabaseCount('agent_tasks', 0);
    }

    public function test_platform_admin_can_reject_via_the_component(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();

        Livewire::actingAs($admin)
            ->test(RetentionPurgeQueue::class)
            ->set('reviewerNotes', 'Not yet.')
            ->call('reject', $proposal->id);

        $this->assertSame('rejected', $proposal->fresh()->status);
        $this->assertSame('Not yet.', $proposal->fresh()->reviewer_notes);
        $this->assertDatabaseCount('agent_tasks', 1);
    }

    public function test_a_regular_user_is_forbidden(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $regularUser = User::factory()->create();

        Livewire::actingAs($regularUser)
            ->test(RetentionPurgeQueue::class)
            ->call('approve', $proposal->id)
            ->assertForbidden();
    }

    public function test_retention_purges_route_requires_authentication(): void
    {
        $this->get('/governance/retention-purges')->assertRedirect('/login');
    }
}
