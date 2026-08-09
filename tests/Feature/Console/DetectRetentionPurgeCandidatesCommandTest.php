<?php

namespace Tests\Feature\Console;

use App\Models\AgentDeployment;
use App\Models\AgentMessage;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Models\RetentionPurgeProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectRetentionPurgeCandidatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_model_with_eligible_rows_gets_exactly_one_pending_proposal(): void
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('retention:detect-purge-candidates')->assertSuccessful();

        $this->assertDatabaseCount('retention_purge_proposals', 1);
        $proposal = RetentionPurgeProposal::first();
        $this->assertSame(AgentTask::class, $proposal->model_class);
        $this->assertSame(1, $proposal->eligible_count);
        $this->assertSame('pending', $proposal->status);
    }

    public function test_a_model_with_no_eligible_rows_gets_no_proposal(): void
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(1), // not yet past the 90-day window
        ]);

        $this->artisan('retention:detect-purge-candidates')->assertSuccessful();

        $this->assertDatabaseCount('retention_purge_proposals', 0);
    }

    public function test_running_the_command_twice_does_not_duplicate_a_pending_proposal(): void
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('retention:detect-purge-candidates');
        $this->artisan('retention:detect-purge-candidates');

        $this->assertDatabaseCount('retention_purge_proposals', 1);
    }

    public function test_a_rejected_proposal_gets_a_fresh_one_on_the_next_eligible_run(): void
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('retention:detect-purge-candidates');

        $existing = RetentionPurgeProposal::where('model_class', AgentTask::class)->firstOrFail();
        $existing->update(['status' => 'rejected']);

        $this->artisan('retention:detect-purge-candidates');

        $this->assertDatabaseCount('retention_purge_proposals', 2);
        $this->assertSame(
            1,
            RetentionPurgeProposal::where('model_class', AgentTask::class)->where('status', 'pending')->count()
        );
    }

    public function test_the_command_does_not_error_on_agent_message_which_has_no_prunable_method(): void
    {
        $this->artisan('retention:detect-purge-candidates')->assertSuccessful();

        $this->assertDatabaseMissing('retention_purge_proposals', ['model_class' => AgentMessage::class]);
    }
}
