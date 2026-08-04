<?php

namespace Tests\Feature\Middleware;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage for the null-organization-context bug pattern seen
 * across the platform ecosystem (see Dot.Mines commit 0cc4362): a user
 * whose organization context cannot be resolved must never crash the page
 * with a null-dereference / findOrFail(null) — either the request is
 * rejected explicitly (403) or the user is auto-provisioned a context.
 */
class OrganizationContextMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function consentedUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'consent_records' => [
                'platform_terms' => ['accepted_at' => now()->toISOString()],
            ],
        ], $attributes));
    }

    #[Test]
    public function a_brand_new_user_with_no_organization_is_auto_provisioned_one_and_does_not_crash(): void
    {
        // Mirrors the ecosystem regression: a user reaching a team/org-scoped
        // page with zero memberships must not hit a null-dereference crash.
        // OrganizationContextMiddleware auto-creates a personal organization
        // for this platform rather than redirecting to a "create org" page.
        $user = $this->consentedUser();
        $this->assertSame(0, $user->organizations()->count());

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $user->refresh();
        $this->assertGreaterThan(0, $user->organizations()->count());
    }

    #[Test]
    public function a_user_whose_session_organization_was_revoked_mid_session_is_rejected_not_crashed(): void
    {
        // Reproduces the exact reachable-null scenario this audit targets:
        // OrganizationContextMiddleware only revalidates the *session* org id
        // against current membership. If that membership was revoked after
        // the session value was set (e.g. removed from the org), the
        // middleware must reject the request explicitly rather than silently
        // continuing with a null org context that crashes downstream
        // Livewire components (e.g. Organization::findOrFail(null)).
        $user = $this->consentedUser();
        $org = Organization::factory()->create();
        // Deliberately do NOT attach the user to $org — simulates a session
        // that still references an organization the user no longer belongs to.

        $response = $this->actingAs($user)
            ->withSession(['current_organization_id' => $org->id])
            ->get('/dashboard');

        $response->assertForbidden();
    }

    #[Test]
    public function a_user_with_a_valid_session_organization_loads_the_dashboard_normally(): void
    {
        $user = $this->consentedUser();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $org->users()->attach($user->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);

        $response = $this->actingAs($user)
            ->withSession(['current_organization_id' => $org->id])
            ->get('/dashboard');

        $response->assertOk();
    }
}
