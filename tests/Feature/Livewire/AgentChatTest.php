<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Agents\AgentChat;
use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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
}
