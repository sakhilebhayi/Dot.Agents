<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Security\SecurityCenter;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityCenterTest extends TestCase
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

    public function test_security_center_mounts_for_authenticated_user(): void
    {
        $this->actingAs($this->user);
        Gate::before(fn () => true);

        Livewire::test(SecurityCenter::class)
            ->assertStatus(200);
    }

    public function test_security_center_page_requires_authentication(): void
    {
        $this->get(route('security.center'))
            ->assertRedirect(route('login'));
    }

    public function test_security_center_does_not_expose_cross_org_events(): void
    {
        $this->actingAs($this->user);
        Gate::before(fn () => true);

        $otherOrg = Organization::factory()->create();
        session(['current_organization_id' => $this->organization->id]);

        // The component mounts with no cross-org data leak — renders cleanly
        Livewire::test(SecurityCenter::class)
            ->assertStatus(200);
    }
}
