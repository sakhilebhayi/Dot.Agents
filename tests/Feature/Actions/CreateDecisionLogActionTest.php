<?php

namespace Tests\Feature\Actions;

use App\Actions\Governance\CreateDecisionLogAction;
use App\Events\DecisionLogCreated;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\DecisionLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CreateDecisionLogActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private AgentDeployment $deployment;

    private AgentTask $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'confidence_threshold' => 70.0,
        ]);
        $this->task = AgentTask::factory()->create([
            'agent_deployment_id' => $this->deployment->id,
            'organization_id' => $this->organization->id,
            'description' => 'Summarise quarterly report',
            'input_data' => ['quarter' => 'Q1 2026'],
        ]);
    }

    public function test_creates_decision_log_with_required_fields(): void
    {
        $this->actingAs($this->user);
        Gate::before(fn () => true);

        $output = [
            'summary' => 'Revenue grew 12% YoY.',
            'confidence' => 85,
        ];

        $log = app(CreateDecisionLogAction::class)->execute($this->deployment, $this->task, $output);

        $this->assertInstanceOf(DecisionLog::class, $log);
        $this->assertDatabaseHas('decision_logs', [
            'id' => $log->id,
            'agent_deployment_id' => $this->deployment->id,
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_high_risk_output_flags_requires_human_review(): void
    {
        $this->actingAs($this->user);
        Gate::before(fn () => true);

        $output = [
            'summary' => 'I am absolutely certain of something completely fabricated.',
            'confidence' => 20,
        ];

        $log = app(CreateDecisionLogAction::class)->execute($this->deployment, $this->task, $output);

        // Low confidence (20) < deployment threshold (70) → requires review
        $this->assertTrue((bool) $log->requires_human_review);
    }

    public function test_requires_authorization(): void
    {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->create();
        $otherDeployment = AgentDeployment::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherTask = AgentTask::factory()->create([
            'agent_deployment_id' => $otherDeployment->id,
            'organization_id' => $otherOrg->id,
        ]);

        $this->expectException(AuthorizationException::class);
        app(CreateDecisionLogAction::class)->execute($otherDeployment, $otherTask, ['confidence' => 80]);
    }

    public function test_fires_decision_log_created_event(): void
    {
        Event::fake([DecisionLogCreated::class]);

        $this->actingAs($this->user);
        Gate::before(fn () => true);

        $output = ['summary' => 'Test decision.', 'confidence' => 85];
        $log = app(CreateDecisionLogAction::class)->execute($this->deployment, $this->task, $output);

        Event::assertDispatched(DecisionLogCreated::class, function (DecisionLogCreated $event) use ($log) {
            return $event->decisionLog->id === $log->id;
        });
    }
}
