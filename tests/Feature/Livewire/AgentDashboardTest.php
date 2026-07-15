<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard\AgentDashboard;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentDashboardTest extends TestCase
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
    }

    #[Test]
    public function it_renders_successfully(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentDashboard::class)
            ->assertOk();
    }

    #[Test]
    public function it_defaults_to_7d_timeframe(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentDashboard::class)
            ->assertSet('timeframe', '7d');
    }

    #[Test]
    public function set_timeframe_updates_property(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentDashboard::class)
            ->call('setTimeframe', '30d')
            ->assertSet('timeframe', '30d');
    }

    #[Test]
    public function deployment_stats_includes_required_keys(): void
    {
        $this->actingAs($this->user);

        AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(AgentDashboard::class);

        $stats = $component->get('deploymentStats');

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('paused', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['total']);
        $this->assertGreaterThanOrEqual(1, $stats['active']);
    }

    #[Test]
    public function task_stats_returns_correct_structure(): void
    {
        $this->actingAs($this->user);

        AgentTask::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'completed',
        ]);

        AgentTask::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'failed',
        ]);

        $stats = Livewire::actingAs($this->user)
            ->test(AgentDashboard::class)
            ->get('taskStats');

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('pending_approval', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['completed']);
    }
}
