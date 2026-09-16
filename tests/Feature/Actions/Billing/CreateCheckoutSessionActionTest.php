<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\CreateCheckoutSessionAction;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CreateCheckoutSessionActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->organization->users()->attach($this->user->id, ['role' => 'owner']);
        $this->plan = SubscriptionPlan::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_creates_checkout_session_via_stripe_service(): void
    {
        $mockSession = new \stdClass;
        $mockSession->id = 'cs_test_123';
        $mockSession->url = 'https://checkout.stripe.com/pay/cs_test_123';

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createCheckoutSession')
            ->once()
            ->with(
                Mockery::on(fn ($org) => $org->id === $this->organization->id),
                Mockery::on(fn ($pl) => $pl->id === $this->plan->id),
                'https://example.com/success',
                'https://example.com/cancel',
            )
            ->andReturn($mockSession);

        $this->app->instance(StripeService::class, $stripe);

        $result = app(CreateCheckoutSessionAction::class)->execute(
            $this->organization,
            $this->plan,
            'https://example.com/success',
            'https://example.com/cancel',
        );

        $this->assertEquals('cs_test_123', $result->id);
    }

    public function test_passes_correct_urls_to_stripe_service(): void
    {
        $mockSession = new \stdClass;
        $mockSession->id = 'cs_url_test';

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createCheckoutSession')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                'https://app.com/billing/success',
                'https://app.com/billing/cancel',
            )
            ->andReturn($mockSession);

        $this->app->instance(StripeService::class, $stripe);

        app(CreateCheckoutSessionAction::class)->execute(
            $this->organization,
            $this->plan,
            'https://app.com/billing/success',
            'https://app.com/billing/cancel',
        );

        // Mockery verifies the shouldReceive assertion above
        $this->assertTrue(true);
    }
}
