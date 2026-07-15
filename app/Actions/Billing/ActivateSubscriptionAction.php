<?php

namespace App\Actions\Billing;

use App\DTOs\Billing\ActivateSubscriptionData;
use App\Events\SubscriptionActivated;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Gate;

class ActivateSubscriptionAction
{
    use \App\Actions\Concerns\LogsActionErrors;

    public function execute(ActivateSubscriptionData $data): OrganizationSubscription
    {
        $organization = Organization::findOrFail($data->organizationId);
        $plan = SubscriptionPlan::findOrFail($data->planId);

        Gate::authorize('manage-billing', $organization);

        // Deactivate any existing active subscription
        OrganizationSubscription::where('organization_id', $organization->id)
            ->whereIn('status', ['active', 'trialing'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $subscription = OrganizationSubscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $data->billingPeriod,
            'amount' => $data->billingPeriod === 'annual' ? ($plan->yearly_price ?? $plan->price ?? 0) : ($plan->price ?? 0),
            'currency' => 'USD',
            'current_period_start' => now(),
            'current_period_end' => $data->billingPeriod === 'annual' ? now()->addYear() : now()->addMonth(),
            'stripe_subscription_id' => $data->stripeSubscriptionId,
            'stripe_customer_id' => $data->stripeCustomerId,
        ]);

        $organization->update(['plan' => $plan->slug ?? strtolower($plan->name)]);

        event(new SubscriptionActivated($subscription));

        return $subscription;
    }
}
