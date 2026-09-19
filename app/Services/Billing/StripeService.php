<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Webhook;

class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create or retrieve a Stripe customer for an organization.
     */
    public function ensureCustomer(Organization $organization): string
    {
        if ($organization->stripe_customer_id) {
            return $organization->stripe_customer_id;
        }

        $customer = $this->stripe->customers->create([
            'name' => $organization->name,
            'email' => $organization->owner->email ?? '',
            'metadata' => [
                'organization_id' => $organization->id,
                'organization_slug' => $organization->slug,
            ],
        ]);

        $organization->update(['stripe_customer_id' => $customer->id]);

        Log::info('StripeService: customer created', [
            'organization_id' => $organization->id,
            'stripe_customer_id' => $customer->id,
        ]);

        return $customer->id;
    }

    /**
     * Create a Stripe Checkout Session for a plan subscription.
     */
    public function createCheckoutSession(
        Organization $organization,
        SubscriptionPlan $plan,
        string $successUrl,
        string $cancelUrl,
        /** @return Session */
    ): object {
        $customerId = $this->ensureCustomer($organization);
        $priceId = $plan->stripe_price_id;

        if (! $priceId) {
            throw new \InvalidArgumentException("Plan [{$plan->id}] has no Stripe price ID configured.");
        }

        return $this->stripe->checkout->sessions->create([
            'customer' => $customerId,
            'mode' => 'subscription',
            'line_items' => [['price' => $priceId, 'quantity' => 1]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'organization_id' => $organization->id,
                'subscription_plan_id' => $plan->id,
            ],
            'subscription_data' => [
                'trial_period_days' => $plan->trial_days > 0 ? $plan->trial_days : null,
                'metadata' => [
                    'organization_id' => $organization->id,
                    'subscription_plan_id' => $plan->id,
                ],
            ],
        ]);
    }

    /**
     * Create a Stripe customer portal session for billing management.
     */
    public function createBillingPortalSession(
        Organization $organization,
        string $returnUrl,
    ): \Stripe\BillingPortal\Session {
        $customerId = $this->ensureCustomer($organization);

        return $this->stripe->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * Retrieve a Stripe subscription.
     */
    /** @return Subscription */
    public function getSubscription(string $stripeSubscriptionId): object
    {
        return $this->stripe->subscriptions->retrieve($stripeSubscriptionId);
    }

    /**
     * Cancel a Stripe subscription at period end.
     */
    /** @return Subscription */
    public function cancelSubscription(string $stripeSubscriptionId): object
    {
        return $this->stripe->subscriptions->update($stripeSubscriptionId, [
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * Validate a Stripe webhook signature and return the event.
     *
     * @throws SignatureVerificationException
     */
    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret'),
        );
    }
}
