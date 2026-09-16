<?php

namespace App\Http\Controllers;

use App\Actions\Billing\CreateCheckoutSessionAction;
use App\Actions\Billing\HandleStripeWebhookAction;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Services\Billing\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class BillingController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly CreateCheckoutSessionAction $createCheckoutSession,
        private readonly HandleStripeWebhookAction $handleWebhook,
    ) {}

    /**
     * Redirect to Stripe Checkout for a plan subscription.
     */
    public function checkout(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $orgId = session('current_organization_id');
        abort_if(! $orgId, 403, 'No active organization context.');
        $organization = Organization::findOrFail($orgId);

        $session = $this->createCheckoutSession->execute(
            organization: $organization,
            plan: $plan,
            successUrl: route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: route('billing.plans'),
        );

        return redirect($session->url);
    }

    /**
     * Handle successful checkout redirect.
     */
    public function success(Request $request): RedirectResponse
    {
        return redirect()->route('dashboard')
            ->with('success', 'Subscription activated successfully. Welcome aboard!');
    }

    /**
     * Redirect to Stripe Customer Portal for billing management.
     */
    public function portal(Request $request): RedirectResponse
    {
        $orgId = session('current_organization_id');
        abort_if(! $orgId, 403, 'No active organization context.');
        $organization = Organization::findOrFail($orgId);

        Gate::authorize('manageBilling', $organization);

        $session = $this->stripe->createBillingPortalSession(
            organization: $organization,
            returnUrl: route('billing.settings.billing'),
        );

        return redirect($session->url);
    }

    /**
     * Handle incoming Stripe webhooks.
     * This route must be excluded from CSRF and auth middleware.
     */
    public function webhook(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = $this->stripe->constructWebhookEvent($payload, $signature);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response('Webhook signature invalid', 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook parse error', ['error' => $e->getMessage()]);

            return response('Webhook error', 400);
        }

        $this->handleWebhook->execute($event);

        return response('OK', 200);
    }
}
