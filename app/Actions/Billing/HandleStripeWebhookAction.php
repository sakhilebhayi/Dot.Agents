<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\SubscriptionPlan;
use App\Notifications\BillingNotification;
use App\Services\Billing\StripeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleStripeWebhookAction
{
    /**
     * Process a verified Stripe webhook event.
     *
     * SECURITY NOTE — No Gate authorization is intentional.
     *
     * This action is invoked exclusively from BillingController::webhook(),
     * which is on a public route (Stripe cannot authenticate as a platform
     * user). Authorization is enforced at the transport layer instead:
     *
     *   1. BillingController::webhook() calls $this->stripe->constructWebhookEvent()
     *      which calls \Stripe\Webhook::constructEvent() with the raw payload and
     *      the Stripe-Signature header, verifying the HMAC-SHA256 signature using
     *      STRIPE_WEBHOOK_SECRET from .env.
     *   2. A SignatureVerificationException results in an immediate 400 response
     *      before this action is ever reached.
     *
     * Adding a Gate authorization check here would be incorrect — it would
     * require an authenticated platform user and would reject all legitimate
     * Stripe events. See the webhook_actions_must_not_have_gate_authorize
     * architecture guard test which enforces this exemption.
     */
    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    public function execute(object $event): void
    {
        // Idempotency guard — prevent replay attacks by rejecting already-processed events
        $cacheKey = 'stripe_event_'.$event->id;
        if (Cache::has($cacheKey)) {
            Log::info('StripeWebhook: duplicate event ignored (idempotency)', ['event_id' => $event->id, 'type' => $event->type]);

            return;
        }
        // Mark as processed for 48 hours (covers Stripe's 24h retry window + buffer)
        Cache::put($cacheKey, true, 172800);

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'invoice.payment_succeeded' => $this->handleInvoicePaid($event->data->object),
            'invoice.payment_failed' => $this->handleInvoiceFailed($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => Log::info('StripeWebhook: unhandled event type', ['type' => $event->type]),
        };
    }

    private function handleCheckoutCompleted(object $session): void
    {
        $organizationId = $session->metadata->organization_id ?? null;
        $planId = $session->metadata->subscription_plan_id ?? null;

        if (! $organizationId || ! $planId) {
            Log::warning('StripeWebhook: checkout.session.completed missing metadata', [
                'session_id' => $session->id,
            ]);

            return;
        }

        $organization = Organization::find($organizationId);
        $plan = SubscriptionPlan::find($planId);

        if (! $organization || ! $plan) {
            return;
        }

        $stripeSubscription = $this->stripe->getSubscription($session->subscription);

        DB::transaction(function () use ($organization, $plan, $stripeSubscription, $session) {
            OrganizationSubscription::updateOrCreate(
                ['organization_id' => $organization->id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'billing_cycle' => $plan->billing_cycle ?? 'monthly',
                    'amount' => $plan->price,
                    'currency' => 'usd',
                    'current_period_start' => now()->setTimestamp($stripeSubscription->current_period_start),
                    'current_period_end' => now()->setTimestamp($stripeSubscription->current_period_end),
                    'stripe_subscription_id' => $stripeSubscription->id,
                    'metadata' => ['stripe_session_id' => $session->id],
                ]
            );
        });

        Log::info('StripeWebhook: subscription activated', [
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
        ]);
    }

    private function handleInvoicePaid(object $stripeInvoice): void
    {
        $organizationId = $stripeInvoice->subscription_details?->metadata->organization_id ?? null;

        if (! $organizationId) {
            return;
        }

        $organization = Organization::find($organizationId);
        if (! $organization) {
            return;
        }

        $subscription = OrganizationSubscription::where('organization_id', $organizationId)
            ->where('stripe_subscription_id', $stripeInvoice->subscription)
            ->first();

        $invoice = Invoice::create([
            'organization_id' => $organizationId,
            'organization_subscription_id' => $subscription?->id,
            'invoice_number' => $stripeInvoice->number ?? 'INV-'.time(),
            'status' => 'paid',
            'subtotal' => $stripeInvoice->subtotal / 100,
            'tax' => ($stripeInvoice->tax ?? 0) / 100,
            'total' => $stripeInvoice->total / 100,
            'currency' => $stripeInvoice->currency,
            'paid_at' => now()->setTimestamp($stripeInvoice->status_transitions->paid_at ?? now()->timestamp),
            'stripe_invoice_id' => $stripeInvoice->id,
            'pdf_url' => $stripeInvoice->invoice_pdf,
        ]);

        $organization->owner?->notify(new BillingNotification('invoice_created', $organization, $invoice));
    }

    private function handleInvoiceFailed(object $stripeInvoice): void
    {
        $organizationId = $stripeInvoice->subscription_details?->metadata->organization_id ?? null;

        if (! $organizationId) {
            return;
        }

        $organization = Organization::find($organizationId);
        if (! $organization) {
            return;
        }

        $organization->owner?->notify(new BillingNotification('payment_failed', $organization));

        Log::warning('StripeWebhook: payment failed', [
            'organization_id' => $organizationId,
            'invoice_id' => $stripeInvoice->id,
        ]);
    }

    private function handleSubscriptionUpdated(object $stripeSubscription): void
    {
        $organizationId = $stripeSubscription->metadata->organization_id ?? null;

        if (! $organizationId) {
            return;
        }

        OrganizationSubscription::where('stripe_subscription_id', $stripeSubscription->id)
            ->update([
                'status' => $stripeSubscription->status,
                'current_period_start' => now()->setTimestamp($stripeSubscription->current_period_start),
                'current_period_end' => now()->setTimestamp($stripeSubscription->current_period_end),
            ]);
    }

    private function handleSubscriptionDeleted(object $stripeSubscription): void
    {
        $organizationId = $stripeSubscription->metadata->organization_id ?? null;

        if (! $organizationId) {
            return;
        }

        OrganizationSubscription::where('stripe_subscription_id', $stripeSubscription->id)
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

        $organization = Organization::find($organizationId);
        $organization?->owner?->notify(new BillingNotification('subscription_cancelled', $organization));
    }
}
