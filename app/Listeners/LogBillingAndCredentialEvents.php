<?php

namespace App\Listeners;

use App\Events\CheckoutSessionCreated;
use App\Events\SocialCredentialsSaved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogBillingAndCredentialEvents implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'billing';

    public int $tries = 3;

    public function handleSocialCredentialsSaved(SocialCredentialsSaved $event): void
    {
        Log::info('social.credentials_saved', [
            'organization_id' => $event->organization->id,
            'platform' => $event->platform,
        ]);
    }

    public function handleCheckoutSessionCreated(CheckoutSessionCreated $event): void
    {
        Log::info('billing.checkout_session_created', [
            'organization_id' => $event->organization->id,
            'plan_id' => $event->plan->id,
            'plan_name' => $event->plan->name,
        ]);
    }
}
