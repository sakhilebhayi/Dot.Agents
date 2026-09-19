<?php

namespace Tests\Feature\Actions\Agents;

use App\Actions\Agents\StartAgentChatSessionAction;
use App\DTOs\Agents\StartAgentChatSessionData;
use App\Events\AgentChatStarted;
use App\Models\AgentDeployment;
use App\Models\AgentSession;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class StartAgentChatSessionActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private AgentDeployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);
        $this->actingAs($this->user);
        Gate::before(fn () => true);
        session(['current_organization_id' => $this->organization->id]);
    }

    #[Test]
    public function test_creates_agent_session(): void
    {
        Event::fake([AgentChatStarted::class]);

        $data = new StartAgentChatSessionData(
            userId: $this->user->id,
            agentDeploymentId: $this->deployment->id,
            organizationId: $this->organization->id,
            title: 'Test Chat',
        );

        $session = app(StartAgentChatSessionAction::class)->execute($this->deployment, $data);

        $this->assertInstanceOf(AgentSession::class, $session);
        $this->assertEquals('active', $session->status);
        $this->assertEquals($this->deployment->id, $session->agent_deployment_id);
        $this->assertEquals($this->user->id, $session->user_id);
        $this->assertDatabaseHas('agent_sessions', ['id' => $session->id]);
        Event::assertDispatched(AgentChatStarted::class);
    }

    #[Test]
    public function test_uses_default_title_when_not_provided(): void
    {
        Event::fake();

        $data = new StartAgentChatSessionData(
            userId: $this->user->id,
            agentDeploymentId: $this->deployment->id,
        );

        $session = app(StartAgentChatSessionAction::class)->execute($this->deployment, $data);

        $this->assertEquals('New Conversation', $session->title);
    }

    #[Test]
    public function test_store_user_message_increments_count(): void
    {
        Event::fake();
        $session = AgentSession::factory()->create([
            'agent_deployment_id' => $this->deployment->id,
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
        ]);

        $action = app(StartAgentChatSessionAction::class);
        $action->storeUserMessage($session, 'Hello agent');

        $this->assertEquals(1, $session->fresh()->message_count);
        $this->assertDatabaseHas('agent_messages', [
            'session_id' => $session->id,
            'role' => 'user',
            'content' => 'Hello agent',
        ]);
    }

    #[Test]
    public function test_end_session_marks_completed(): void
    {
        Event::fake();
        $session = AgentSession::factory()->create([
            'agent_deployment_id' => $this->deployment->id,
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
        ]);

        app(StartAgentChatSessionAction::class)->endSession($session);

        $this->assertEquals('completed', $session->fresh()->status);
        $this->assertNotNull($session->fresh()->ended_at);
    }

    #[Test]
    public function test_constructor_does_not_inject_the_unused_audit_service(): void
    {
        // Audit logging for chat session start happens in the LogAgentChatStarted
        // listener (bound to AgentChatStarted), not in this action — its own
        // AuditService dependency was never called (phpstan property.onlyWritten).
        $constructor = (new ReflectionClass(StartAgentChatSessionAction::class))->getConstructor();

        $this->assertTrue($constructor === null || $constructor->getNumberOfParameters() === 0);
    }
}
