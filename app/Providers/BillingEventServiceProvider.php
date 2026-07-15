<?php

namespace App\Providers;

use App\Events\CheckoutSessionCreated;
use App\Events\SocialCredentialsSaved;
use App\Events\SubscriptionActivated;
use App\Listeners\LogBillingAndCredentialEvents;
use App\Listeners\LogSubscriptionActivated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Billing, subscription, and credential event bindings.
 */
class BillingEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SubscriptionActivated::class => [
            LogSubscriptionActivated::class,
        ],

        CheckoutSessionCreated::class => [
            LogBillingAndCredentialEvents::class.'@handleCheckoutSessionCreated',
        ],

        SocialCredentialsSaved::class => [
            LogBillingAndCredentialEvents::class.'@handleSocialCredentialsSaved',
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
