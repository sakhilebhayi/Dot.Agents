<?php

namespace Tests\Feature\Livewire;

use App\Jobs\ProcessAgentMessage;
use App\Livewire\Agents\AgentChat;
use App\Models\AgentDeployment;
use App\Models\AgentMessage;
use App\Models\AgentSession;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AgentChatTest extends TestCase
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
        session(['current_organization_id' => $this->organization->id]);

        $this->deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);

        Gate::before(fn () => true);
    }

    public function test_chat_component_mounts(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentChat::class, ['deploymentId' => $this->deployment->id])
            ->assertSet('deploymentId', $this->deployment->id)
            ->assertSet('message', '')
            ->assertSet('isTyping', false);
    }

    public function test_message_property_is_bound(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentChat::class, ['deploymentId' => $this->deployment->id])
            ->set('message', 'Hello agent!')
            ->assertSet('message', 'Hello agent!');
    }

    public function test_send_message_validates_empty_message(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentChat::class, ['deploymentId' => $this->deployment->id])
            ->set('message', '')
            ->call('sendMessage')
            ->assertHasErrors(['message' => 'required']);
    }

    public function test_chat_component_renders(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentChat::class, ['deploymentId' => $this->deployment->id])
            ->assertStatus(200);
    }

    public function test_send_message_validates_max_length(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentChat::class, ['deploymentId' => $this->deployment->id])
            ->set('message', str_repeat('a', 10001))
            ->call('sendMessage')
            ->assertHasErrors(['message' => 'max']);
    }

    public function test_show_task_panel_can_be_toggled(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentChat::class, ['deploymentId' => $this->deployment->id])
            ->assertSet('showTaskPanel', false)
            ->set('showTaskPanel', true)
            ->assertSet('showTaskPanel', true);
    }

    public function test_chat_route_requires_authentication(): void
    {
        $this->get("/my-agents/{$this->deployment->id}/chat")
            ->assertRedirect();
    }

    // -----------------------------------------------------------------------
    // Async processing on the 'ai' queue
    // -----------------------------------------------------------------------

    public function test_send_message_dispatches_processing_to_the_ai_queue_instead_of_running_inline(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentChat::class, ['deploymentId' => $this->deployment->id])
            ->set('message', 'Hello agent!')
            ->call('sendMessage')
            ->assertSet('isTyping', true);

        Queue::assertPushedOn('ai', ProcessAgentMessage::class);
    }

    public function test_send_message_persists_the_user_message_immediately_even_though_the_reply_is_queued(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentChat::class, ['deploymentId' => $this->deployment->id])
            ->set('message', 'Hello agent!')
            ->call('sendMessage');

        $this->assertDatabaseHas('agent_messages', ['role' => 'user', 'content' => 'Hello agent!']);
    }

    public function test_poll_for_reply_clears_typing_state_once_an_assistant_message_arrives(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        $component = Livewire::actingAs($this->user)
            ->test(AgentChat::class, ['deploymentId' => $this->deployment->id])
            ->set('message', 'Hello agent!')
            ->call('sendMessage')
            ->assertSet('isTyping', true);

        // Nothing has replied yet -- still typing.
        $component->call('pollForReply')->assertSet('isTyping', true);

        // Simulate the queued job completing.
        AgentMessage::create([
            'session_id' => $component->get('sessionId'),
            'role' => 'assistant',
            'content' => 'Hi there!',
        ]);

        $component->call('pollForReply')->assertSet('isTyping', false);
    }

    public function test_session_cost_displays_the_sessions_real_cost_column(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::actingAs($this->user)
            ->test(AgentChat::class, ['deploymentId' => $this->deployment->id]);

        // agent_sessions.cost is decimal(10,6) — must stay within that precision
        AgentSession::whereKey($component->get('sessionId'))->update(['cost' => 123.4567]);

        $component->call('pollForReply')
            ->assertSee(number_format(123.4567, 4));
    }
}
