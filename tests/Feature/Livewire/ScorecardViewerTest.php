<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Agents\ScorecardViewer;
use App\Models\AgentDeployment;
use App\Models\AgentScorecard;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScorecardViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private AgentDeployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        // The component is #[Lazy]; Livewire::test() otherwise only renders
        // the placeholder and never calls mount().
        Livewire::withoutLazyLoading();

        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        session(['current_organization_id' => $this->organization->id]);

        $this->deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);
    }

    public function test_history_table_displays_the_real_tasks_completed_column(): void
    {
        $this->actingAs($this->user);

        // An older row, distinct from the "current" scorecard below, so its
        // value can only come from the History table -- never from the
        // current-period stat cards -- proving the table reads the real
        // column rather than accidentally matching the current period.
        AgentScorecard::factory()->create([
            'agent_deployment_id' => $this->deployment->id,
            'organization_id' => $this->organization->id,
            'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
            'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
            'tasks_completed' => 111222,
        ]);

        AgentScorecard::factory()->create([
            'agent_deployment_id' => $this->deployment->id,
            'organization_id' => $this->organization->id,
            'tasks_completed' => 999888,
        ]);

        Livewire::actingAs($this->user)
            ->test(ScorecardViewer::class, ['deploymentId' => $this->deployment->id])
            ->assertSee('111,222');
    }
}
