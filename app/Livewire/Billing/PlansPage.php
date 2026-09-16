<?php

namespace App\Livewire\Billing;

use App\Models\OrganizationSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PlansPage extends Component
{
    #[Computed]
    public function plans(): Collection
    {
        return SubscriptionPlan::active()->public()->ordered()->get();
    }

    #[Computed]
    public function currentSubscription(): ?OrganizationSubscription
    {
        return OrganizationSubscription::with('plan')
            ->where('status', 'active')
            ->first();
    }

    public function render()
    {
        return view('livewire.billing.plans-page');
    }
}
