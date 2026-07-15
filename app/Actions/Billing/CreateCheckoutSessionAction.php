<?php

namespace App\Actions\Billing;

use App\Events\CheckoutSessionCreated;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Services\Billing\StripeService;
use Illuminate\Support\Facades\Gate;
use Stripe\Checkout\Session;

class CreateCheckoutSessionAction
{
    use \App\Actions\Concerns\LogsActionErrors;

    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    /** @return Session */
    public function execute(
        Organization $organization,
        SubscriptionPlan $plan,
        string $successUrl,
        string $cancelUrl,
    ): object {
        Gate::authorize('manage-billing', $organization);

        $session = $this->stripe->createCheckoutSession(
            $organization,
            $plan,
            $successUrl,
            $cancelUrl,
        );

        event(new CheckoutSessionCreated($organization, $plan));

        return $session;
    }
}
