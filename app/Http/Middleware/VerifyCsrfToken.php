<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * Stripe webhooks must be excluded because Stripe cannot send a CSRF token.
     * Security is enforced instead via Stripe-Signature header verification
     * in BillingController::webhook() using \Stripe\Webhook::constructEvent().
     *
     * Social platform webhooks must be excluded for the same reason. Security
     * is enforced instead via X-Hub-Signature-256 header verification in
     * SocialWebhookController::receive().
     *
     * @var array<int, string>
     */
    protected $except = [
        '/webhooks/stripe',
        '/social/webhooks/*',
    ];
}
