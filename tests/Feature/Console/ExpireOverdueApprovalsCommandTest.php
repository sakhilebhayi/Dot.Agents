<?php

namespace Tests\Feature\Console;

use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentSkillApproval;
use App\Models\AgentTask;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireOverdueApprovalsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function agentApproval(string $status, string $expiresAt): AgentApproval
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        $task = AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
        ]);

        return AgentApproval::factory()->create([
            'task_id' => $task->id,
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }

    public function test_an_overdue_pending_agent_approval_becomes_expired(): void
    {
        $approval = $this->agentApproval('pending', now()->subHour()->toDateTimeString());

        $this->artisan('approvals:expire-overdue')->assertSuccessful();

        $this->assertSame('expired', $approval->fresh()->status);
    }

    public function test_a_pending_agent_approval_still_within_its_window_is_untouched(): void
    {
        $approval = $this->agentApproval('pending', now()->addHour()->toDateTimeString());

        $this->artisan('approvals:expire-overdue')->assertSuccessful();

        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_an_already_approved_overdue_agent_approval_is_untouched(): void
    {
        $approval = $this->agentApproval('approved', now()->subHour()->toDateTimeString());

        $this->artisan('approvals:expire-overdue')->assertSuccessful();

        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_an_overdue_pending_skill_approval_becomes_expired(): void
    {
        $skillApproval = AgentSkillApproval::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('approvals:expire-overdue')->assertSuccessful();

        $this->assertSame('expired', $skillApproval->fresh()->status);
    }

    public function test_a_skill_approval_still_within_its_window_is_untouched(): void
    {
        $skillApproval = AgentSkillApproval::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('approvals:expire-overdue')->assertSuccessful();

        $this->assertSame('pending', $skillApproval->fresh()->status);
    }
}
