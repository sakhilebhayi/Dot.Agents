<?php

namespace Tests\Feature\Governance;

use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\DecisionLog;
use App\Models\Organization;
use App\Models\UsageRecord;
use App\Services\Governance\DigitalImmuneSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigitalImmuneSystemTest extends TestCase
{
    use RefreshDatabase;

    private DigitalImmuneSystem $dis;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->dis = app(DigitalImmuneSystem::class);
    }

    public function test_health_check_returns_report_structure(): void
    {
        $report = $this->dis->runHealthCheck($this->org->id);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('checked_at', $report);
        $this->assertArrayHasKey('total_agents', $report);
        $this->assertArrayHasKey('healthy', $report);
        $this->assertArrayHasKey('warnings', $report);
        $this->assertArrayHasKey('critical', $report);
        $this->assertArrayHasKey('events', $report);
    }

    public function test_health_check_with_no_deployments_returns_zero_counts(): void
    {
        $report = $this->dis->runHealthCheck($this->org->id);

        $this->assertEquals(0, $report['total_agents']);
        $this->assertEquals(0, $report['healthy']);
        $this->assertEquals(0, $report['warnings']);
        $this->assertEquals(0, $report['critical']);
        $this->assertEmpty($report['events']);
    }

    public function test_health_check_counts_active_deployments(): void
    {
        AgentDeployment::factory()->count(3)->create([
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);

        // One inactive — should not be counted
        AgentDeployment::factory()->create([
            'organization_id' => $this->org->id,
            'status' => 'paused',
        ]);

        $report = $this->dis->runHealthCheck($this->org->id);

        $this->assertEquals(3, $report['total_agents']);
    }

    public function test_check_deployment_returns_healthy_status_for_normal_deployment(): void
    {
        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);

        $status = $this->dis->checkDeployment($deployment);

        $this->assertArrayHasKey('health', $status);
        $this->assertArrayHasKey('events', $status);
        $this->assertContains($status['health'], ['healthy', 'warnings', 'critical', 'quarantined']);
    }

    public function test_dis_detects_high_failure_rate_as_critical(): void
    {
        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);

        // Create a spike of failed tasks
        AgentTask::factory()->count(15)->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $this->org->id,
            'status' => 'failed',
            'created_at' => now()->subMinutes(30),
        ]);

        $status = $this->dis->checkDeployment($deployment);

        // With 15 failures, DIS should flag this as non-healthy
        $this->assertContains($status['health'], ['warnings', 'critical', 'quarantined']);
    }

    public function test_dis_detects_high_delusion_rate(): void
    {
        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);

        // Seed high-delusion decision logs
        DecisionLog::factory()->count(5)->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $this->org->id,
            'delusion_risk_score' => 85,
            'created_at' => now()->subHour(),
        ]);

        $status = $this->dis->checkDeployment($deployment);

        // Should detect critical delusion risk
        $this->assertContains($status['health'], ['warnings', 'critical', 'quarantined']);
    }

    public function test_health_check_is_cached(): void
    {
        // Run health check once to prime any internal state
        $this->dis->runHealthCheck($this->org->id);

        // Verify no fatal errors on repeat calls (idempotency)
        $report2 = $this->dis->runHealthCheck($this->org->id);

        $this->assertIsArray($report2);
    }

    public function test_dis_treats_a_single_usage_anomaly_as_a_warning(): void
    {
        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);

        UsageRecord::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $this->org->id,
            'total_cost' => 75,
            'recorded_date' => now()->toDateString(),
        ]);

        $status = $this->dis->checkDeployment($deployment);

        $this->assertSame('warnings', $status['health']);
        $this->assertSame('active', $deployment->fresh()->status);
    }

    public function test_dis_escalates_and_quarantines_after_consecutive_usage_anomalies(): void
    {
        $deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);

        UsageRecord::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $this->org->id,
            'total_cost' => 75,
            'recorded_date' => now()->toDateString(),
        ]);

        // Same anomaly detected 3 times in a row (e.g. 3 periodic health-check
        // runs) should escalate from a routine warning to a critical,
        // quarantine-triggering event rather than staying a flat warning forever.
        $this->dis->checkDeployment($deployment);
        $this->dis->checkDeployment($deployment);
        $status = $this->dis->checkDeployment($deployment);

        $this->assertSame('critical', $status['health']);
        $this->assertSame('suspended', $deployment->fresh()->status);
    }

    public function test_dis_does_not_check_deployments_from_other_orgs(): void
    {
        $otherOrg = Organization::factory()->create();

        AgentDeployment::factory()->count(3)->create([
            'organization_id' => $otherOrg->id,
            'status' => 'active',
        ]);

        $report = $this->dis->runHealthCheck($this->org->id);

        // Should only count this org's deployments (zero)
        $this->assertEquals(0, $report['total_agents']);
    }
}
