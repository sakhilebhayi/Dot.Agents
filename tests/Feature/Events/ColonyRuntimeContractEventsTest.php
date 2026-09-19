<?php

namespace Tests\Feature\Events;

use App\Events\AgentCharterDriftDetected;
use App\Events\AgentRunContractBreach;
use App\Listeners\LogAgentCharterDriftDetectedAudit;
use App\Listeners\LogAgentRunContractBreachAudit;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ColonyRuntimeContractEventsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private AgentDeployment $deployment;

    private AgentTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $user->id]);
        $this->deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->task = AgentTask::factory()->create([
            'agent_deployment_id' => $this->deployment->id,
            'organization_id' => $this->organization->id,
        ]);
    }

    // ── AgentRunContractBreach ───────────────────────────────────────────────

    public function test_agent_run_contract_breach_is_registered_to_its_audit_listener(): void
    {
        Event::fake([AgentRunContractBreach::class]);

        event(new AgentRunContractBreach($this->deployment, $this->task, 'wall_clock', 620.0, 300.0));

        Event::assertListening(AgentRunContractBreach::class, LogAgentRunContractBreachAudit::class);
    }

    public function test_agent_run_contract_breach_listener_writes_a_real_audit_log_row(): void
    {
        event(new AgentRunContractBreach($this->deployment, $this->task, 'tool_calls', 55.0, 40.0));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'agent_run_contract_breach',
            'agent_deployment_id' => $this->deployment->id,
        ]);

        /** @var AuditLog $log */
        $log = AuditLog::where('event', 'agent_run_contract_breach')->firstOrFail();

        $this->assertSame($this->task->id, $log->new_values['task_id']);
        $this->assertSame('tool_calls', $log->new_values['bound_type']);
        $this->assertEquals(55.0, $log->new_values['measured_value']);
        $this->assertEquals(40.0, $log->new_values['limit_value']);
    }

    public function test_agent_run_contract_breach_rejects_an_unknown_bound_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AgentRunContractBreach($this->deployment, $this->task, 'memory', 1.0, 1.0);
    }

    // ── AgentCharterDriftDetected ────────────────────────────────────────────

    public function test_agent_charter_drift_detected_is_registered_to_its_audit_listener(): void
    {
        Event::fake([AgentCharterDriftDetected::class]);

        event(new AgentCharterDriftDetected($this->deployment, 'Agent acted outside its charter scope'));

        Event::assertListening(AgentCharterDriftDetected::class, LogAgentCharterDriftDetectedAudit::class);
    }

    public function test_agent_charter_drift_detected_listener_writes_a_real_audit_log_row(): void
    {
        event(new AgentCharterDriftDetected($this->deployment, 'Agent drafted governance-domain content outside its charter'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'agent_charter_drift_detected',
            'agent_deployment_id' => $this->deployment->id,
        ]);

        /** @var AuditLog $log */
        $log = AuditLog::where('event', 'agent_charter_drift_detected')->firstOrFail();

        $this->assertSame(
            'Agent drafted governance-domain content outside its charter',
            $log->new_values['description']
        );
    }
}
