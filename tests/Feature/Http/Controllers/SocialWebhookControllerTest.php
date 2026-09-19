<?php

namespace Tests\Feature\Http\Controllers;

use App\Jobs\GenerateSocialResponseJob;
use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\SocialConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SocialWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test_facebook_app_secret';

    private const VERIFY_TOKEN = 'test_facebook_verify_token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.facebook.client_secret' => self::APP_SECRET,
            'services.facebook.webhook_verify_token' => self::VERIFY_TOKEN,
        ]);
    }

    public function test_verification_challenge_succeeds_with_correct_token(): void
    {
        $response = $this->get('/social/webhooks/facebook?hub.mode=subscribe&hub.verify_token='.self::VERIFY_TOKEN.'&hub.challenge=1234567890');

        $response->assertOk();
        $response->assertSeeText('1234567890');
    }

    public function test_verification_challenge_fails_with_incorrect_token(): void
    {
        $response = $this->get('/social/webhooks/facebook?hub.mode=subscribe&hub.verify_token=wrong_token&hub.challenge=1234567890');

        $response->assertStatus(403);
    }

    public function test_valid_signed_message_creates_conversation_and_dispatches_job(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        $agentDeployment = AgentDeployment::factory()->create(['organization_id' => $organization->id]);
        $socialAccount = SocialAccount::factory()->create([
            'organization_id' => $organization->id,
            'platform' => 'facebook',
            'platform_account_id' => 'page_123456',
            'agent_deployment_id' => $agentDeployment->id,
        ]);

        $payload = $this->buildPayload('page_123456', 'psid_abc', 'mid.001', 'hello, world!');

        $response = $this->postSignedWebhook($payload);

        $response->assertOk();
        $response->assertSeeText('EVENT_RECEIVED');

        $this->assertDatabaseHas('social_conversations', [
            'social_account_id' => $socialAccount->id,
            'contact_platform_id' => 'psid_abc',
            'platform' => 'facebook',
            'channel_type' => 'messenger',
            'organization_id' => $organization->id,
        ]);

        $this->assertDatabaseHas('social_messages', [
            'content' => 'hello, world!',
            'direction' => 'inbound',
            'sender_id' => 'psid_abc',
        ]);

        Queue::assertPushed(GenerateSocialResponseJob::class);
    }

    public function test_invalid_signature_returns_403_and_creates_nothing(): void
    {
        Queue::fake();

        $socialAccount = SocialAccount::factory()->create([
            'platform' => 'facebook',
            'platform_account_id' => 'page_123456',
        ]);

        $payload = $this->buildPayload('page_123456', 'psid_abc', 'mid.001', 'hello, world!');

        $response = $this->postJson('/social/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => 'sha256=deadbeef',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('social_conversations', 0);
        $this->assertDatabaseCount('social_messages', 0);
        Queue::assertNotPushed(GenerateSocialResponseJob::class);
    }

    public function test_missing_signature_returns_403_and_creates_nothing(): void
    {
        $payload = $this->buildPayload('page_123456', 'psid_abc', 'mid.001', 'hello, world!');

        $response = $this->postJson('/social/webhooks/facebook', $payload);

        $response->assertStatus(403);
        $this->assertDatabaseCount('social_conversations', 0);
    }

    public function test_unknown_page_id_returns_200_and_creates_nothing(): void
    {
        $payload = $this->buildPayload('page_does_not_exist', 'psid_abc', 'mid.001', 'hello, world!');

        $response = $this->postSignedWebhook($payload);

        $response->assertOk();
        $this->assertDatabaseCount('social_conversations', 0);
        $this->assertDatabaseCount('social_messages', 0);
    }

    public function test_delivery_receipt_without_message_returns_200_and_creates_nothing(): void
    {
        SocialAccount::factory()->create([
            'platform' => 'facebook',
            'platform_account_id' => 'page_123456',
        ]);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'page_123456',
                    'time' => 1458692752478,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'psid_abc'],
                            'recipient' => ['id' => 'page_123456'],
                            'timestamp' => 1458692752478,
                            'delivery' => [
                                'mids' => ['mid.001'],
                                'watermark' => 1458692752478,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload);

        $response->assertOk();
        $this->assertDatabaseCount('social_conversations', 0);
        $this->assertDatabaseCount('social_messages', 0);
    }

    public function test_second_message_from_same_sender_reuses_conversation(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        SocialAccount::factory()->create([
            'organization_id' => $organization->id,
            'platform' => 'facebook',
            'platform_account_id' => 'page_123456',
        ]);

        $first = $this->buildPayload('page_123456', 'psid_abc', 'mid.001', 'first message');
        $this->postSignedWebhook($first)->assertOk();

        $this->assertDatabaseCount('social_conversations', 1);

        $second = $this->buildPayload('page_123456', 'psid_abc', 'mid.002', 'second message');
        $this->postSignedWebhook($second)->assertOk();

        $this->assertDatabaseCount('social_conversations', 1);
        $this->assertDatabaseCount('social_messages', 2);
    }

    private function buildPayload(string $pageId, string $psid, string $mid, string $text): array
    {
        return [
            'object' => 'page',
            'entry' => [
                [
                    'id' => $pageId,
                    'time' => 1458692752478,
                    'messaging' => [
                        [
                            'sender' => ['id' => $psid],
                            'recipient' => ['id' => $pageId],
                            'timestamp' => 1458692752478,
                            'message' => [
                                'mid' => $mid,
                                'text' => $text,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function postSignedWebhook(array $payload)
    {
        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha256', $rawBody, self::APP_SECRET);

        return $this->postJson('/social/webhooks/facebook', $payload, [
            'X-Hub-Signature-256' => 'sha256='.$signature,
        ]);
    }
}
