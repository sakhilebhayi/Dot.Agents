<?php

namespace App\Listeners;

use App\Events\SubscriptionActivated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogSubscriptionActivated implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'billing';

    public int $tries = 3;

    public function handle(SubscriptionActivated $event): void
    {
        Log::channel('single')->info('[Billing] Subscription activated', [
            'organization_id' => $event->subscription->organization_id,
            'plan_id' => $event->subscription->plan_id,
            'billing_cycle' => $event->subscription->billing_cycle,
            'status' => $event->subscription->status,
        ]);
    }
}
