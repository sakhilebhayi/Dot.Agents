<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard\AgentDashboard;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        session(['current_organization_id' => $this->organization->id]);
    }

    #[Test]
    public function it_renders_successfully(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentDashboard::class)
            ->assertOk();
    }

    #[Test]
    public function it_defaults_to_7d_timeframe(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentDashboard::class)
            ->assertSet('timeframe', '7d');
    }

    #[Test]
    public function set_timeframe_updates_property(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(AgentDashboard::class)
            ->call('setTimeframe', '30d')
            ->assertSet('timeframe', '30d');
    }
}
