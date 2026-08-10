<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessAgentMessage;
use App\Models\AgentDeployment;
use App\Models\AgentMessage;
use App\Models\AgentSession;
use App\Services\AI\AgentOrchestrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessAgentMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_processes_the_message_and_persists_an_assistant_reply(): void
    {
        config(['prism.default_provider' => 'mock']);

        $deployment = AgentDeployment::factory()->create(['status' => 'active']);
        $session = AgentSession::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $deployment->organization_id,
        ]);

        (new ProcessAgentMessage($deployment, $session, 'Hello agent!'))->handle(app(AgentOrchestrationService::class));

        $this->assertDatabaseHas('agent_messages', [
            'session_id' => $session->id,
            'role' => 'assistant',
        ]);
    }

    public function test_failed_leaves_a_visible_error_reply_instead_of_an_indefinite_typing_state(): void
    {
        $deployment = AgentDeployment::factory()->create(['status' => 'active']);
        $session = AgentSession::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $deployment->organization_id,
        ]);

        $job = new ProcessAgentMessage($deployment, $session, 'Hello agent!');
        $job->failed(new \RuntimeException('all providers exhausted'));

        $reply = AgentMessage::where('session_id', $session->id)->where('role', 'assistant')->first();

        $this->assertNotNull($reply);
        $this->assertSame('error', $reply->metadata['finish_reason'] ?? null);
    }
}
