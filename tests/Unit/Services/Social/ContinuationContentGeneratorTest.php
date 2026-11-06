<?php

namespace Tests\Unit\Services\Social;

use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\SocialConversation;
use App\Models\SocialMessage;
use App\Services\Models\ScriptedGateway;
use App\Services\Social\ContinuationContentGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContinuationContentGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private SocialConversation $conversation;

    private AgentDeployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::factory()->create();
        $account = SocialAccount::factory()->create(['organization_id' => $organization->id]);

        $this->deployment = AgentDeployment::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Ada',
            'custom_instructions' => null,
            'model_override' => null,
        ]);

        $this->conversation = SocialConversation::factory()->create([
            'organization_id' => $organization->id,
            'social_account_id' => $account->id,
            'agent_deployment_id' => $this->deployment->id,
        ]);
    }

    private function inboundMessage(string $content = 'Do you have a demo I can book?'): SocialMessage
    {
        return SocialMessage::create([
            'organization_id' => $this->conversation->organization_id,
            'social_conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'contact',
            'sender_id' => 'contact_1',
            'sender_name' => 'A Customer',
            'content' => $content,
            'message_type' => 'text',
            'status' => 'sent',
        ]);
    }

    private function fakeAiResponse(array $reply = ['content' => 'Happy to help you book a demo!', 'strategy' => 'schedule_demo', 'confidence' => 91]): ScriptedGateway
    {
        config(['prism.default_provider' => 'scripted']);

        return app(ScriptedGateway::class)->say((string) json_encode((object) $reply));
    }

    public function test_generate_returns_parsed_ai_content(): void
    {
        $this->fakeAiResponse();
        $inbound = $this->inboundMessage();

        $result = app(ContinuationContentGenerator::class)->generate(
            conversation: $this->conversation,
            inboundMessage: $inbound,
            deployment: $this->deployment,
            intentResult: [],
            shouldDiscloseAi: true,
        );

        $this->assertSame('Happy to help you book a demo!', $result['content']);
        $this->assertSame('schedule_demo', $result['strategy']);
        $this->assertSame(91.0, $result['confidence']);
    }

    public function test_generate_falls_back_to_defaults_when_ai_response_omits_fields(): void
    {
        $this->fakeAiResponse([]);
        $inbound = $this->inboundMessage();

        $result = app(ContinuationContentGenerator::class)->generate(
            conversation: $this->conversation,
            inboundMessage: $inbound,
            deployment: $this->deployment,
            intentResult: [],
            shouldDiscloseAi: false,
        );

        $this->assertSame('Thank you for your message. How can I assist you further?', $result['content']);
        $this->assertSame('general_engagement', $result['strategy']);
        $this->assertSame(80.0, $result['confidence']);
    }

    public function test_disclosure_instruction_is_included_only_when_required(): void
    {
        $model = $this->fakeAiResponse();
        $inbound = $this->inboundMessage();

        app(ContinuationContentGenerator::class)->generate(
            conversation: $this->conversation,
            inboundMessage: $inbound,
            deployment: $this->deployment,
            intentResult: [],
            shouldDiscloseAi: true,
        );

        $this->assertStringContainsString('naturally disclose you are an AI assistant', $model->requests[0]->system);
    }

    public function test_disclosure_instruction_is_omitted_when_not_required(): void
    {
        $model = $this->fakeAiResponse();
        $inbound = $this->inboundMessage();

        app(ContinuationContentGenerator::class)->generate(
            conversation: $this->conversation,
            inboundMessage: $inbound,
            deployment: $this->deployment,
            intentResult: [],
            shouldDiscloseAi: false,
        );

        $this->assertStringNotContainsString('naturally disclose you are an AI assistant', $model->requests[0]->system);
    }

    public function test_deployment_model_override_is_used_when_set(): void
    {
        $this->deployment->update(['model_override' => 'gpt-4o']);
        $model = $this->fakeAiResponse();
        $inbound = $this->inboundMessage();

        app(ContinuationContentGenerator::class)->generate(
            conversation: $this->conversation,
            inboundMessage: $inbound,
            deployment: $this->deployment->fresh(),
            intentResult: [],
            shouldDiscloseAi: false,
        );

        $this->assertSame('gpt-4o', $model->requests[0]->model);
    }

    public function test_recent_conversation_history_is_replayed_in_order(): void
    {
        SocialMessage::create([
            'organization_id' => $this->conversation->organization_id,
            'social_conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'contact',
            'sender_id' => 'contact_1',
            'sender_name' => 'A Customer',
            'content' => 'first turn',
            'message_type' => 'text',
            'status' => 'sent',
        ])->forceFill(['created_at' => now()->subMinutes(2)])->saveQuietly();

        SocialMessage::create([
            'organization_id' => $this->conversation->organization_id,
            'social_conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'sender_id' => (string) $this->deployment->id,
            'sender_name' => $this->deployment->name,
            'content' => 'first reply',
            'message_type' => 'text',
            'status' => 'sent',
        ])->forceFill(['created_at' => now()->subMinute()])->saveQuietly();

        $inbound = $this->inboundMessage('second turn');

        $model = $this->fakeAiResponse();

        app(ContinuationContentGenerator::class)->generate(
            conversation: $this->conversation,
            inboundMessage: $inbound,
            deployment: $this->deployment,
            intentResult: [],
            shouldDiscloseAi: false,
        );

        $this->assertSame([
            ['role' => 'user', 'text' => 'first turn'],
            ['role' => 'assistant', 'text' => 'first reply'],
            ['role' => 'user', 'text' => 'second turn'],
        ], $model->requests[0]->messages);
    }
}
