<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    private function consentedUser(): User
    {
        return User::factory()->create([
            'consent_records' => [
                'platform_terms' => ['accepted_at' => now()->toISOString()],
            ],
        ]);
    }

    #[Test]
    public function guests_are_redirected_away_from_the_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function an_authenticated_and_consented_user_can_load_the_dashboard(): void
    {
        $user = $this->consentedUser();
        Organization::factory()->create(['owner_id' => $user->id])
            ->users()->attach($user->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('AI Workforce Dashboard');
    }

    #[Test]
    public function a_user_without_consent_is_redirected_to_the_consent_page(): void
    {
        $user = User::factory()->create(['consent_records' => null]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('consent.show'));
    }

    #[Test]
    public function the_marketplace_page_loads_for_an_authenticated_user(): void
    {
        $user = $this->consentedUser();
        Organization::factory()->create(['owner_id' => $user->id])
            ->users()->attach($user->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);

        $response = $this->actingAs($user)->get('/marketplace');

        $response->assertOk();
    }

    #[Test]
    public function the_my_agents_deployments_page_loads_for_an_authenticated_user(): void
    {
        $user = $this->consentedUser();
        Organization::factory()->create(['owner_id' => $user->id])
            ->users()->attach($user->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);

        $response = $this->actingAs($user)->get('/my-agents');

        $response->assertOk();
    }
}
