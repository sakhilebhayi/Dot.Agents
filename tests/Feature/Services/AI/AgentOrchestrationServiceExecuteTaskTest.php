<?php

namespace Tests\Feature\Services\AI;

use App\Events\AgentRunContractBreach;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\DecisionLog;
use App\Services\AI\AgentModelCaller;
use App\Services\AI\AgentOrchestrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the Phase 4 wiring in AgentOrchestrationService::executeTask():
 * Dot.Memory context/decision/action recording and the AgentCharterLoader
 * provisional-runtime-contract enforcement.
 *
 * Every deployment created by deployment() below is deliberately left
 * UNCHARTERED (no matching Dot.Brain charter file) unless a test explicitly
 * points services.dot_brain.charters_path at the fixture directory and
 * assigns a fixture slug — this mirrors production, where every current
 * Dot.Agents deployment is uncharted by default.
 *
 * Note: Event::fake() with no arguments replaces Eloquent's model event
 * dispatcher wholesale, which silently defeats the uuid-generation
 * `static::creating()` hooks on DecisionLog/AgentTask/AgentDeployment. Tests
 * below that need both a real DecisionLog insert and an
 * Event::fake()-observed AgentRunContractBreach therefore scope the fake to
 * that one event class.
 */
class AgentOrchestrationServiceExecuteTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.dot_memory.base_url' => null,
            'services.dot_memory.token' => null,
            'services.dot_brain.charters_path' => base_path('../Dot.Brain/agents'),
        ]);
    }

    private function configureDotMemory(): void
    {
        config([
            'services.dot_memory.base_url' => 'https://memory.test',
            'services.dot_memory.token' => 'test-token',
        ]);
    }

    private function useCharterFixtures(): void
    {
        config(['services.dot_brain.charters_path' => base_path('tests/Fixtures/dot-brain-charters')]);
    }

    private function outputJson(float $confidence): string
    {
        return json_encode([
            'summary' => 'Analysis complete.',
            'result' => ['trend' => 'stable'],
            'confidence' => $confidence,
            'reasoning' => 'Based on the provided metrics, the trend is stable.',
            'evidence' => ['metric shows stability', 'period Q3 data confirms trend'],
            'assumptions' => [],
            'risks' => [],
            'recommendations' => [],
            'impact_score' => 50,
        ]);
    }

    private function mockModelCaller(array $overrides = []): void
    {
        $response = array_merge([
            'content' => $this->outputJson(90.0),
            'usage' => ['total_tokens' => 100, 'prompt_tokens' => 60, 'completion_tokens' => 40],
            'cost' => 0.01,
            'model_used' => 'gpt-4o',
            'finish_reason' => 'stop',
            'provider' => 'openai',
        ], $overrides);

        $this->mock(AgentModelCaller::class, function ($mock) use ($response) {
            $mock->shouldReceive('callWithFailover')->andReturn($response);
        });
    }

    private function deployment(array $attrs = []): AgentDeployment
    {
        return AgentDeployment::factory()->create(array_merge([
            'status' => 'active',
            'deployment_mode' => 'advisory',
            'requires_human_approval' => false,
            'confidence_threshold' => 50.0,
        ], $attrs));
    }

    private function task(AgentDeployment $deployment, array $attrs = []): AgentTask
    {
        return AgentTask::factory()->create(array_merge([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $deployment->organization_id,
            'status' => 'pending',
            'task_type' => 'analysis',
            'input_data' => ['metric' => 'signups', 'period' => 'Q3'],
        ], $attrs));
    }

    private function orchestrator(): AgentOrchestrationService
    {
        return $this->app->make(AgentOrchestrationService::class);
    }

    /**
     * Executes a task expected to require approval and asserts the real,
     * fixed outcome: the task lands in 'awaiting_approval' and a real
     * AgentApproval row is created (this used to crash with an ErrorException
     * from an undefined $approval variable in
     * ResponseProcessorService::createApprovalRequest() — fixed alongside
     * this phase, since the provisional-charter rule now makes this path
     * fire on every task for every uncharted agent, i.e. effectively all of
     * them today).
     */
    private function executeExpectingApproval(AgentDeployment $deployment, AgentTask $task): AgentTask
    {
        $completedTask = $this->orchestrator()->executeTask($deployment, $task);

        $this->assertSame('awaiting_approval', $completedTask->status);
        $this->assertDatabaseHas('agent_approvals', ['task_id' => $task->id, 'status' => 'pending']);

        return $completedTask;
    }

    // -----------------------------------------------------------------------
    // context() + dot_memory_context_degraded metadata flag
    // -----------------------------------------------------------------------

    public function test_context_is_called_and_degraded_flag_is_true_when_dot_memory_is_unconfigured(): void
    {
        $deployment = $this->deployment();
        $task = $this->task($deployment);

        Http::fake();
        $this->mockModelCaller(['content' => $this->outputJson(95.0)]);

        $this->executeExpectingApproval($deployment, $task);

        Http::assertNothingSent();

        $task->refresh();
        $this->assertTrue($task->metadata['dot_memory_context_degraded']);
    }

    public function test_context_is_called_and_degraded_flag_is_false_when_dot_memory_call_succeeds(): void
    {
        $this->configureDotMemory();
        $deployment = $this->deployment();
        $task = $this->task($deployment);

        Http::fake([
            'memory.test/api/intelligence/context*' => Http::response(['data' => [
                'subject' => ['type' => 'agent_deployment', 'id' => (string) $deployment->id],
                'known' => false,
                'observations' => 0,
                'first_seen' => null,
                'last_seen' => null,
                'timeline' => [],
                'what_was_tried' => [],
                'what_worked' => [],
                'recurrence' => [],
                'gaps' => [],
            ]], 200),
            'memory.test/*' => Http::response(['data' => ['id' => 1]], 201),
        ]);

        $this->mockModelCaller(['content' => $this->outputJson(95.0)]);

        $this->executeExpectingApproval($deployment, $task);

        Http::assertSent(function ($request) use ($deployment) {
            return str_contains($request->url(), 'api/intelligence/context')
                && $request['subject_type'] === 'agent_deployment'
                && $request['subject_id'] === (string) $deployment->id;
        });

        $task->refresh();
        $this->assertFalse($task->metadata['dot_memory_context_degraded']);
    }

    // -----------------------------------------------------------------------
    // Charter / provisional runtime contract enforcement
    // -----------------------------------------------------------------------

    public function test_uncharted_agent_forces_approval_even_when_confidence_and_delusion_alone_would_not_require_it(): void
    {
        $deployment = $this->deployment(['requires_human_approval' => false, 'confidence_threshold' => 50.0]);
        $task = $this->task($deployment);

        $this->mockModelCaller(['content' => $this->outputJson(95.0)]);

        $this->executeExpectingApproval($deployment, $task);

        $decisionLog = DecisionLog::where('task_id', $task->id)->first();
        $this->assertNotNull($decisionLog);
        $this->assertTrue((bool) $decisionLog->requires_human_review);
        $this->assertEqualsWithDelta(95.0, (float) $decisionLog->confidence_score, 0.01);
        // Neither confidence (95 >= 50 threshold, approval not required) nor
        // delusion risk (well under 60) explain the escalation above — only
        // the uncharted/provisional force does.
        $this->assertLessThan(60, (float) $decisionLog->delusion_risk_score);
    }

    public function test_chartered_agent_below_trust_floor_forces_approval(): void
    {
        $this->useCharterFixtures();

        $deployment = $this->deployment(['requires_human_approval' => false, 'confidence_threshold' => 40.0]);
        $deployment->agent->update(['slug' => 'governance']); // fixture: trust-score-floor 0.60
        $task = $this->task($deployment);

        $this->mockModelCaller(['content' => $this->outputJson(50.0)]); // 0.50 < 0.60 floor

        $this->executeExpectingApproval($deployment, $task);

        $decisionLog = DecisionLog::where('task_id', $task->id)->first();
        $this->assertNotNull($decisionLog);
        $this->assertTrue((bool) $decisionLog->requires_human_review);
        // 50 is not below the 40 confidence_threshold and delusion risk is low,
        // so only the charter's own trust-score-floor probation rule explains it.
        $this->assertLessThan(60, (float) $decisionLog->delusion_risk_score);
    }

    public function test_chartered_agent_above_trust_floor_and_confident_completes_without_forced_approval(): void
    {
        $this->useCharterFixtures();
        $this->configureDotMemory();

        $deployment = $this->deployment(['requires_human_approval' => false, 'confidence_threshold' => 50.0]);
        $deployment->agent->update(['slug' => 'governance']); // fixture: trust-score-floor 0.60
        $task = $this->task($deployment);

        Http::fake(['memory.test/*' => Http::response(['data' => ['id' => 1]], 201)]);

        $this->mockModelCaller(['content' => $this->outputJson(90.0)]); // 0.90 >= 0.60 floor

        $completedTask = $this->orchestrator()->executeTask($deployment, $task);

        $this->assertSame('completed', $completedTask->status);

        $decisionLog = DecisionLog::where('task_id', $task->id)->first();
        $this->assertNotNull($decisionLog);
        $this->assertFalse((bool) $decisionLog->requires_human_review);

        Http::assertSent(function ($request) use ($task) {
            $body = $request->data();

            return str_contains($request->url(), 'api/intelligence/decisions')
                && $body['subject_type'] === 'agent_task'
                && $body['subject_id'] === (string) $task->id
                && abs($body['confidence'] - 0.90) < 0.0001
                && $body['autonomy_level'] === 'observe' // advisory -> observe
                && $body['requires_approval'] === false;
        });

        Http::assertSent(function ($request) use ($task) {
            $body = $request->data();

            return str_contains($request->url(), 'api/intelligence/actions')
                && $body['subject_type'] === 'agent_task'
                && $body['subject_id'] === (string) $task->id
                && $body['action_kind'] === 'analysis'
                && $body['execution_status'] === 'succeeded';
        });
    }

    // -----------------------------------------------------------------------
    // Resource-bound contract breaches (provisional agents only)
    // -----------------------------------------------------------------------

    public function test_wall_clock_contract_breach_dispatches_event_and_suspends_the_provisional_deployment(): void
    {
        Event::fake([AgentRunContractBreach::class]);
        config(['services.dot_brain.provisional_wall_clock_ms' => 0]);

        $deployment = $this->deployment();
        $task = $this->task($deployment);

        $response = [
            'content' => $this->outputJson(95.0),
            'usage' => ['total_tokens' => 10],
            'cost' => 0.001,
            'model_used' => 'gpt-4o',
            'finish_reason' => 'stop',
            'provider' => 'openai',
        ];

        $this->mock(AgentModelCaller::class, function ($mock) use ($response) {
            $mock->shouldReceive('callWithFailover')->andReturnUsing(function () use ($response) {
                usleep(2000);

                return $response;
            });
        });

        $this->executeExpectingApproval($deployment, $task);

        Event::assertDispatched(AgentRunContractBreach::class, function ($event) use ($deployment, $task) {
            return $event->boundType === 'wall_clock'
                && $event->deployment->id === $deployment->id
                && $event->task->id === $task->id
                && $event->measuredValue > 0
                && $event->limitValue === 0.0;
        });

        $this->assertSame('suspended', $deployment->fresh()->status);
    }

    public function test_tool_calls_contract_breach_dispatches_event_and_suspends_the_provisional_deployment(): void
    {
        Event::fake([AgentRunContractBreach::class]);
        config(['services.dot_brain.provisional_max_tool_calls' => 1]);

        $deployment = $this->deployment();
        $task = $this->task($deployment);

        $this->mockModelCaller([
            'content' => $this->outputJson(95.0),
            'tool_calls' => [['name' => 'search'], ['name' => 'search'], ['name' => 'fetch']],
        ]);

        $this->executeExpectingApproval($deployment, $task);

        Event::assertDispatched(AgentRunContractBreach::class, function ($event) use ($deployment, $task) {
            return $event->boundType === 'tool_calls'
                && $event->deployment->id === $deployment->id
                && $event->task->id === $task->id
                && $event->measuredValue === 3.0
                && $event->limitValue === 1.0;
        });

        $this->assertSame('suspended', $deployment->fresh()->status);
    }

    public function test_chartered_agent_does_not_trigger_resource_bound_contract_breach(): void
    {
        Event::fake([AgentRunContractBreach::class]);
        $this->useCharterFixtures();
        config(['services.dot_brain.provisional_wall_clock_ms' => 0]);
        config(['services.dot_brain.provisional_max_tool_calls' => 0]);

        $deployment = $this->deployment(['requires_human_approval' => false, 'confidence_threshold' => 50.0]);
        $deployment->agent->update(['slug' => 'governance']);
        $task = $this->task($deployment);

        $this->mockModelCaller([
            'content' => $this->outputJson(90.0),
            'tool_calls' => [['name' => 'search']],
        ]);

        $completedTask = $this->orchestrator()->executeTask($deployment, $task);

        $this->assertSame('completed', $completedTask->status);
        Event::assertNotDispatched(AgentRunContractBreach::class);
        $this->assertSame('active', $deployment->fresh()->status);
    }
}
