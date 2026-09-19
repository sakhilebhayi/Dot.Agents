<?php

namespace Tests\Feature\Actions\Social;

use App\Actions\Social\EscalateConversationAction;
use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\SocialConversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EscalateConversationActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->user->organizations()->attach($this->organization->id, ['role' => 'owner']);
        $this->actingAs($this->user);
    }

    #[Test]
    public function test_escalates_conversation(): void
    {
        $account = SocialAccount::factory()->create(['organization_id' => $this->organization->id]);
        $conversation = SocialConversation::factory()->create([
            'organization_id' => $this->organization->id,
            'social_account_id' => $account->id,
            'status' => 'open',
            'is_escalated' => false,
        ]);

        $result = app(EscalateConversationAction::class)->execute(
            $conversation,
            escalatedTo: $this->user->id,
            escalatedBy: $this->user->id,
            reason: 'Customer is very upset',
        );

        $this->assertEquals('escalated', $result->status);
        $this->assertTrue((bool) $result->is_escalated);
        $this->assertTrue((bool) $result->requires_human);
        $this->assertEquals('urgent', $result->priority);
        $this->assertNotNull($result->escalated_at);
    }

    #[Test]
    public function test_unauthorized_user_cannot_escalate_conversation(): void
    {
        $account = SocialAccount::factory()->create(['organization_id' => $this->organization->id]);
        $conversation = SocialConversation::factory()->create([
            'organization_id' => $this->organization->id,
            'social_account_id' => $account->id,
            'status' => 'open',
            'is_escalated' => false,
        ]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider);

        $this->expectException(AuthorizationException::class);

        app(EscalateConversationAction::class)->execute(
            $conversation,
            escalatedTo: $outsider->id,
            escalatedBy: $outsider->id,
            reason: 'Customer is very upset',
        );
    }
}
