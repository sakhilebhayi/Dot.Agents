<?php

namespace Tests\Feature\Actions\Social;

use App\Actions\Social\RespondToSocialMessageAction;
use App\DTOs\Social\SocialMessageResponseData;
use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\SocialConversation;
use App\Models\SocialMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RespondToSocialMessageActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private SocialConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->user->organizations()->attach($this->organization->id, ['role' => 'owner']);
        $account = SocialAccount::factory()->create(['organization_id' => $this->organization->id]);
        $this->conversation = SocialConversation::factory()->create([
            'organization_id' => $this->organization->id,
            'social_account_id' => $account->id,
            'status' => 'open',
            'first_response_at' => null,
        ]);
        $this->actingAs($this->user);
    }

    #[Test]
    public function test_stores_outbound_message(): void
    {
        $data = new SocialMessageResponseData(
            organizationId: $this->organization->id,
            socialConversationId: $this->conversation->id,
            content: 'Thanks for reaching out!',
        );

        $result = app(RespondToSocialMessageAction::class)->execute($data, $this->user->id);

        $this->assertInstanceOf(SocialMessage::class, $result);
        $this->assertDatabaseHas('social_messages', [
            'social_conversation_id' => $this->conversation->id,
            'content' => 'Thanks for reaching out!',
        ]);
    }

    #[Test]
    public function test_receives_inbound_message(): void
    {
        $result = app(RespondToSocialMessageAction::class)->receiveInbound(
            organizationId: $this->organization->id,
            socialConversationId: $this->conversation->id,
            content: 'Hello, I have a question',
            senderPlatformId: 'fb_user_123',
            senderName: 'John Public',
        );

        $this->assertInstanceOf(SocialMessage::class, $result);
        $this->assertDatabaseHas('social_messages', [
            'content' => 'Hello, I have a question',
            'direction' => 'inbound',
        ]);
    }
}
