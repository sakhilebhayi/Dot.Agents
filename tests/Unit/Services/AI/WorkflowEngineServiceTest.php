<?php

namespace Tests\Unit\Services\AI;

use App\Models\AgentWorkflow;
use App\Services\AI\AgentOrchestrationService;
use App\Services\AI\Workflow\WorkflowStepExecutor;
use App\Services\AI\WorkflowEngineService;
use App\Services\Governance\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkflowEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeEngine(WorkflowStepExecutor $stepExecutor): WorkflowEngineService
    {
        $auditService = Mockery::mock(AuditService::class);
        $auditService->shouldReceive('logUserAction')->zeroOrMoreTimes();

        return new WorkflowEngineService(
            Mockery::mock(AgentOrchestrationService::class),
            $auditService,
            $stepExecutor,
        );
    }

    #[Test]
    public function a_hard_step_failure_marks_the_execution_failed_instead_of_crashing(): void
    {
        $workflow = AgentWorkflow::factory()->create([
            'steps' => [
                ['type' => 'data_transform', 'halt_on_failure' => true],
            ],
        ]);

        $stepExecutor = Mockery::mock(WorkflowStepExecutor::class);
        $stepExecutor->shouldReceive('evaluateCondition')->andReturn(true);
        $stepExecutor->shouldReceive('executeStep')->andReturn(['status' => 'failed', 'error' => 'boom']);

        $execution = $this->makeEngine($stepExecutor)->start($workflow);

        $this->assertSame('failed', $execution->status);
        $this->assertSame('Step 0 failed: boom', $execution->error_message);
        $this->assertNotNull($execution->completed_at);
    }

    #[Test]
    public function an_exception_during_step_execution_marks_the_execution_failed_instead_of_crashing(): void
    {
        $workflow = AgentWorkflow::factory()->create([
            'steps' => [
                ['type' => 'agent_task'],
            ],
        ]);

        $stepExecutor = Mockery::mock(WorkflowStepExecutor::class);
        $stepExecutor->shouldReceive('evaluateCondition')->andReturn(true);
        $stepExecutor->shouldReceive('executeStep')->andThrow(new \RuntimeException('orchestrator unavailable'));

        $execution = $this->makeEngine($stepExecutor)->start($workflow);

        $this->assertSame('failed', $execution->status);
        $this->assertSame('orchestrator unavailable', $execution->error_message);
        $this->assertNotNull($execution->completed_at);
    }
}
