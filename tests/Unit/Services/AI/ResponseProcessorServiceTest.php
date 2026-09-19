<?php

namespace Tests\Unit\Services\AI;

use App\Events\ApprovalRequested;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\DecisionLog;
use App\Services\AI\ResponseProcessorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Regression: AgentApproval::create()'s return value was never captured
 * into $approval, so event(new ApprovalRequested($approval)) referenced an
 * undefined variable — $approval evaluated to null, which then failed
 * ApprovalRequested's non-nullable AgentApproval $approval constructor
 * type-hint with a TypeError. Every call to createApprovalRequest() crashed.
 */
class ResponseProcessorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_approval_request_persists_a_real_approval_and_dispatches_it_with_the_real_model(): void
    {
        Event::fake([ApprovalRequested::class]);

        $deployment = AgentDeployment::factory()->create();
        $task = AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $deployment->organization_id,
            'confidence_score' => 62.0,
            'risk_score' => 80.0,
        ]);
        $decisionLog = DecisionLog::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $deployment->organization_id,
            'task_id' => $task->id,
        ]);

        app(ResponseProcessorService::class)->createApprovalRequest(
            $deployment,
            $task,
            $decisionLog,
            ['risk_score' => 75.0],
        );

        $this->assertDatabaseHas('agent_approvals', [
            'task_id' => $task->id,
            'agent_deployment_id' => $deployment->id,
            'status' => 'pending',
            'risk_level' => 'high',
        ]);

        Event::assertDispatched(ApprovalRequested::class, function (ApprovalRequested $event) use ($task) {
            return $event->approval !== null
                && $event->approval->task_id === $task->id
                && $event->approval->exists;
        });
    }
}
