<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Social\ConnectedAccounts;
use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConnectedAccountsTest extends TestCase
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
        Gate::before(fn () => true);
    }

    #[Test]
    public function it_renders_successfully(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(ConnectedAccounts::class)
            ->assertStatus(200);
    }

    #[Test]
    public function platform_status_returns_array(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::actingAs($this->user)
            ->test(ConnectedAccounts::class);

        $status = $component->get('platformStatus');
        $this->assertIsArray($status);
    }

    #[Test]
    public function it_opens_manage_panel_for_platform(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(ConnectedAccounts::class)
            ->call('openManage', 'twitter')
            ->assertSet('managing', 'twitter');
    }

    #[Test]
    public function it_closes_manage_panel(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(ConnectedAccounts::class)
            ->call('openManage', 'twitter')
            ->call('closeManage')
            ->assertSet('managing', null);
    }

    #[Test]
    public function it_shows_connected_accounts_in_platform_status(): void
    {
        $this->actingAs($this->user);

        SocialAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'platform' => 'twitter',
            'status' => 'active',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(ConnectedAccounts::class);

        $status = $component->get('platformStatus');

        // platformStatus is keyed by platform name
        $this->assertArrayHasKey('twitter', $status);
        $this->assertTrue($status['twitter']['connected'] ?? false);
    }
}
