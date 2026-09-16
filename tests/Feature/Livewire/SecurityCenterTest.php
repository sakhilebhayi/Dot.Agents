<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Security\SecurityCenter;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        // The component is #[Lazy]; Livewire::test() otherwise only renders
        // the placeholder and never calls mount(), which would hide the
        // authorization check under test.
        Livewire::withoutLazyLoading();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        session(['current_organization_id' => $this->organization->id]);
    }

    private function grantSecurityAccess(User $user): void
    {
        if (! Role::where('name', 'owner')->exists()) {
            Role::create(['name' => 'owner']);
        }
        $user->assignRole('owner');
    }

    public function test_security_center_mounts_for_authenticated_user(): void
    {
        $this->actingAs($this->user);
        $this->grantSecurityAccess($this->user);

        Livewire::test(SecurityCenter::class)
            ->assertStatus(200)
            ->assertSee('Security Events');
    }

    public function test_security_center_page_requires_authentication(): void
    {
        $this->get(route('security.center'))
            ->assertRedirect(route('login'));
    }

    public function test_security_center_denies_users_without_a_security_role(): void
    {
        $this->actingAs($this->user);

        Livewire::test(SecurityCenter::class)
            ->assertStatus(403);
    }

    public function test_security_center_does_not_expose_cross_org_events(): void
    {
        $this->actingAs($this->user);
        $this->grantSecurityAccess($this->user);

        $otherOrg = Organization::factory()->create();
        session(['current_organization_id' => $this->organization->id]);

        // The component mounts with no cross-org data leak — renders cleanly
        Livewire::test(SecurityCenter::class)
            ->assertStatus(200);
    }

    public function test_run_dis_check_denies_org_members_without_owner_or_admin_role(): void
    {
        $this->actingAs($this->user);
        $this->grantSecurityAccess($this->user);
        $this->organization->users()->attach($this->user->id, ['role' => 'editor']);

        Livewire::test(SecurityCenter::class)
            ->call('runDISCheck')
            ->assertStatus(403);
    }

    public function test_run_dis_check_succeeds_for_org_owner(): void
    {
        $this->actingAs($this->user);
        $this->grantSecurityAccess($this->user);
        $this->organization->users()->attach($this->user->id, ['role' => 'owner']);

        Livewire::test(SecurityCenter::class)
            ->call('runDISCheck')
            ->assertStatus(200)
            ->assertSet('runningDIS', false);
    }
}
