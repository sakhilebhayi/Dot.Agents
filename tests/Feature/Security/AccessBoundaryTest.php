<?php

namespace Tests\Feature\Security;

use App\Actions\Ecosystem\SyncOrganizationsFromInfoDot;
use App\Livewire\Organizations\ApiKeyManager;
use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\NewAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

class AccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organization $organization, string $role = 'owner'): User
    {
        $user = User::factory()->withPersonalTeam()->create(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $organization->users()->attach($user->id, ['role' => $role, 'is_primary' => $organization->users()->count() === 0, 'joined_at' => now()]);

        return $user;
    }

    /** @param  list<string>  $abilities */
    private function key(User $user, Organization $organization, array $abilities): NewAccessToken
    {
        $token = $user->createToken('org-'.$organization->id.':k', $abilities);
        $token->accessToken->forceFill(['organization_id' => $organization->id])->save();

        return $token;
    }

    private function freshRequest(): void
    {
        session()->forget('current_organization_id');
        app('auth')->forgetGuards();
    }

    public function test_a_read_key_cannot_change_anything(): void
    {
        $org = Organization::factory()->create();
        $user = $this->member($org);
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id, 'status' => 'active', 'deployed_by' => $user->id]);
        $key = $this->key($user, $org, ['read'])->plainTextToken;

        $this->withToken($key)->getJson("/api/v1/deployments/{$deployment->id}")->assertOk();
        $this->freshRequest();
        $this->withToken($key)->postJson("/api/v1/deployments/{$deployment->id}/pause")->assertForbidden();

        $this->assertSame('active', $deployment->fresh()->status);
    }

    public function test_a_key_works_for_its_own_business_whichever_is_open(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $user = $this->member($first);
        $this->member($second)->delete();
        $second->users()->attach($user->id, ['role' => 'owner', 'is_primary' => false, 'joined_at' => now()]);
        $inFirst = AgentDeployment::factory()->create(['organization_id' => $first->id, 'deployed_by' => $user->id]);
        $inSecond = AgentDeployment::factory()->create(['organization_id' => $second->id, 'deployed_by' => $user->id]);
        $key = $this->key($user, $second, ['read'])->plainTextToken;

        $this->withToken($key)->getJson("/api/v1/deployments/{$inSecond->id}")->assertOk();
        $this->freshRequest();
        $this->withToken($key)->getJson("/api/v1/deployments/{$inFirst->id}")->assertNotFound();
    }

    public function test_a_key_stops_working_when_its_holder_leaves_the_business(): void
    {
        $org = Organization::factory()->create();
        $user = $this->member($org);
        $key = $this->key($user, $org, ['read'])->plainTextToken;
        $org->users()->detach($user->id);

        $this->withToken($key)->getJson('/api/v1/deployments')->assertForbidden();
    }

    public function test_keys_for_a_business_go_when_infodot_removes_the_person(): void
    {
        $org = Organization::factory()->create(['infodot_team_uuid' => 'gone-uuid']);
        $user = $this->member($org, 'admin');
        $this->key($user, $org, ['read']);

        app(SyncOrganizationsFromInfoDot::class)($user, collect(), null);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_only_someone_who_manages_api_access_can_make_a_key(): void
    {
        $org = Organization::factory()->create();
        $operator = $this->member($org, 'operator');
        session(['current_organization_id' => $org->id]);

        Livewire::actingAs($operator)->test(ApiKeyManager::class)->set('newKeyName', 'Nope')->call('createToken')->assertForbidden();
    }

    public function test_a_new_key_is_bound_to_the_business_with_what_was_ticked_and_an_expiry(): void
    {
        $org = Organization::factory()->create();
        $developer = $this->member($org, 'developer');
        session(['current_organization_id' => $org->id]);

        Livewire::actingAs($developer)->test(ApiKeyManager::class)
            ->set('newKeyName', 'Pipeline')->set('newKeyAbilities', ['read', 'run', 'everything'])->call('createToken')->assertHasNoErrors();

        $token = $developer->tokens()->sole();
        $this->assertSame($org->id, $token->organization_id);
        $this->assertSame(['read', 'run'], $token->abilities);
        $this->assertEqualsWithDelta(90, now()->diffInDays($token->expires_at), 1);
    }

    public function test_in_sso_mode_local_pages_go_to_infodot_and_password_posts_are_refused(): void
    {
        config(['ecosystem.sso_only' => true, 'ecosystem.platforms.infodot.url' => 'https://infodot.test']);
        $user = User::factory()->create(['password' => bcrypt('a-long-password')]);

        $this->get('/login')->assertRedirect('https://infodot.test/login');
        $this->get('/forgot-password')->assertRedirect('https://infodot.test/forgot-password');
        $this->withoutMiddleware(PreventRequestForgery::class)
            ->post('/login', ['email' => $user->email, 'password' => 'a-long-password'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_with_sso_off_password_sign_in_still_works_as_the_way_back(): void
    {
        config(['ecosystem.sso_only' => false]);

        $this->get('/login')->assertOk();
    }

    public function test_a_session_past_its_day_goes_back_through_infodot(): void
    {
        config(['ecosystem.sso_only' => true, 'ecosystem.platforms.infodot.url' => 'https://infodot.test']);
        $org = Organization::factory()->create();
        $user = $this->member($org);

        $this->actingAs($user)->withSession(['infodot_verified_at' => now()->subHours(13)->getTimestamp()])
            ->get('/dashboard')->assertRedirect(route('login'));
        $this->assertFalse(auth('web')->check());
    }

    public function test_a_fresh_session_carries_on(): void
    {
        config(['ecosystem.sso_only' => true]);
        $org = Organization::factory()->create();
        $user = $this->member($org);

        $this->actingAs($user)->withSession(['infodot_verified_at' => now()->subHours(2)->getTimestamp()])
            ->get('/dashboard')->assertOk();
    }
}
