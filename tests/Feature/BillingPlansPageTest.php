<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingPlansPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'consent_records' => [
                'platform_terms' => ['accepted_at' => now()->toISOString()],
            ],
        ]);

        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id]);
        $this->organization->users()->attach($this->owner->id, [
            'role' => 'owner',
            'is_primary' => true,
            'joined_at' => now(),
        ]);

        session(['current_organization_id' => $this->organization->id]);
    }

    #[Test]
    public function it_renders_real_active_public_plans_from_the_database(): void
    {
        SubscriptionPlan::factory()->create([
            'name' => 'Momentum Tier',
            'slug' => 'momentum-tier',
            'price' => 137.00,
            'yearly_price' => 1370.00,
            'features' => ['Dedicated Success Manager', 'Custom Onboarding'],
            'is_active' => true,
            'is_public' => true,
        ]);

        SubscriptionPlan::factory()->create([
            'name' => 'Hidden Internal Tier',
            'slug' => 'hidden-internal-tier',
            'is_active' => true,
            'is_public' => false,
        ]);

        SubscriptionPlan::factory()->create([
            'name' => 'Retired Tier',
            'slug' => 'retired-tier',
            'is_active' => false,
            'is_public' => true,
        ]);

        $response = $this->actingAs($this->owner)->get(route('billing.plans'));

        $response->assertOk();
        $response->assertSee('Momentum Tier');
        $response->assertSee('137');
        $response->assertSee('Dedicated Success Manager');
        $response->assertSee('Custom Onboarding');
        $response->assertDontSee('Hidden Internal Tier');
        $response->assertDontSee('Retired Tier');
    }

    #[Test]
    public function checkout_post_resolves_the_real_plan_slug_and_does_not_404(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Momentum Tier',
            'slug' => 'momentum-tier',
            'is_active' => true,
            'is_public' => true,
        ]);

        $this->assertStringEndsWith('/billing/checkout/momentum-tier', route('billing.checkout', $plan));

        $mockSession = new \stdClass;
        $mockSession->id = 'cs_test_plans_page';
        $mockSession->url = 'https://checkout.stripe.com/pay/cs_test_plans_page';

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn($mockSession);

        $this->app->instance(StripeService::class, $stripe);

        $response = $this->actingAs($this->owner)
            ->post(route('billing.checkout', $plan));

        $response->assertStatus(302);
        $response->assertRedirect('https://checkout.stripe.com/pay/cs_test_plans_page');
    }
}
