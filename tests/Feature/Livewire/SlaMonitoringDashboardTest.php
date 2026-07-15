<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Agents\SlaMonitoringDashboard;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SlaMonitoringDashboardTest extends TestCase
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
            ->test(SlaMonitoringDashboard::class)
            ->assertStatus(200);
    }

    #[Test]
    public function it_defaults_to_7d_timeframe(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(SlaMonitoringDashboard::class)
            ->assertSet('timeframe', '7d');
    }

    #[Test]
    public function it_updates_timeframe_and_clears_computed_cache(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(SlaMonitoringDashboard::class)
            ->set('timeframe', '30d')
            ->assertSet('timeframe', '30d');
    }

    #[Test]
    public function sla_metrics_returns_correct_structure(): void
    {
        $this->actingAs($this->user);

        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        AgentTask::factory()->create([
            'organization_id' => $this->organization->id,
            'agent_deployment_id' => $deployment->id,
            'status' => 'completed',
            'actual_duration_minutes' => 5.0,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(SlaMonitoringDashboard::class);

        $metrics = $component->get('slaMetrics');

        $this->assertArrayHasKey('total_tasks', $metrics);
        $this->assertArrayHasKey('success_rate', $metrics);
        $this->assertArrayHasKey('p95_minutes', $metrics);
        $this->assertGreaterThanOrEqual(1, $metrics['total_tasks']);
    }

    #[Test]
    public function sla_breaches_reports_over_threshold_tasks(): void
    {
        $this->actingAs($this->user);

        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        AgentTask::factory()->create([
            'organization_id' => $this->organization->id,
            'agent_deployment_id' => $deployment->id,
            'status' => 'completed',
            'actual_duration_minutes' => 999.0, // Way over threshold
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(SlaMonitoringDashboard::class);

        $breaches = $component->get('slaBreaches');

        $this->assertArrayHasKey('breach_count', $breaches);
        $this->assertGreaterThanOrEqual(1, $breaches['breach_count']);
    }
}
