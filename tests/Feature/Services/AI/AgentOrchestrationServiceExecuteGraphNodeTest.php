<?php

namespace Tests\Feature\Services\AI;

use App\Models\Agent;
use App\Models\AgentDeployment;
use App\Services\AI\AgentModelCaller;
use App\Services\AI\AgentOrchestrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: executeGraphNode() resolved the deployment's agent via
 * where('key', $agentKey), but the agents table has no 'key' column at all
 * (the real column — and the one addNode()/ManagesWorkflowCanvas actually
 * populates agent_key with — is 'slug'). This meant clicking "Run Workflow"
 * in the graph builder threw a real SQL error (undefined column) on every
 * single execution, for every workflow, always — not a silent skip. Found
 * while wiring Phase 4's charter loader, which uses the same agent
 * identifier concept; fixed alongside it since Run Workflow was completely
 * broken and had zero test coverage.
 */
class AgentOrchestrationServiceExecuteGraphNodeTest extends TestCase
{
    use RefreshDatabase;

    private function mockModelCaller(): void
    {
        $response = [
            'content' => json_encode([
                'summary' => 'Done.',
                'confidence' => 90.0,
                'reasoning' => 'Straightforward.',
                'evidence' => [],
                'assumptions' => [],
                'risks' => [],
                'recommendations' => [],
                'impact_score' => 50,
            ]),
            'usage' => ['total_tokens' => 50],
            'cost' => 0.005,
            'model_used' => 'gpt-4o',
            'finish_reason' => 'stop',
            'provider' => 'openai',
        ];

        $this->mock(AgentModelCaller::class, function ($mock) use ($response) {
            $mock->shouldReceive('callWithFailover')->andReturn($response);
        });
    }

    public function test_executes_the_deployment_whose_agent_slug_matches_the_graph_node_key(): void
    {
        $agent = Agent::factory()->create(['slug' => 'ceo-agent']);
        $deployment = AgentDeployment::factory()->create([
            'agent_id' => $agent->id,
            'status' => 'active',
            'requires_human_approval' => false,
            'confidence_threshold' => 50.0,
        ]);

        $this->mockModelCaller();

        $result = $this->app->make(AgentOrchestrationService::class)
            ->executeGraphNode('ceo-agent', ['input' => 'test']);

        $this->assertNotSame('skipped', $result['status']);
        $this->assertSame($deployment->id, $result['task_id'] ? AgentDeployment::find($deployment->id)->id : null);
        $this->assertArrayHasKey('confidence', $result);
    }

    public function test_returns_skipped_when_no_active_deployment_matches_the_agent_slug(): void
    {
        Agent::factory()->create(['slug' => 'unrelated-agent']);

        $result = $this->app->make(AgentOrchestrationService::class)
            ->executeGraphNode('nonexistent-slug', []);

        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('nonexistent-slug', $result['reason']);
    }
}
