<?php

namespace App\Livewire\Billing;

use App\Models\Invoice;
use App\Models\OrganizationSubscription;
use App\Models\SubscriptionPlan;
use App\Models\UsageRecord;
use Livewire\Attributes\Computed;
use Livewire\Component;

class BillingDashboard extends Component
{
    #[Computed]
    public function organizationId(): ?int
    {
        return session('current_organization_id');
    }

    #[Computed]
    public function subscription(): ?OrganizationSubscription
    {
        return OrganizationSubscription::with('plan')
            ->where('organization_id', $this->organizationId)
            ->where('status', 'active')
            ->first();
    }

    #[Computed]
    public function plans()
    {
        return SubscriptionPlan::where('is_active', true)->orderBy('price')->get();
    }

    #[Computed]
    public function currentMonthUsage(): array
    {
        $records = UsageRecord::where('organization_id', $this->organizationId)
            ->whereMonth('recorded_date', now()->month)
            ->whereYear('recorded_date', now()->year)
            ->get();

        return [
            'tokens' => $records->where('metric_type', 'tokens')->sum('quantity'),
            'tasks' => $records->where('metric_type', 'tasks')->sum('quantity'),
            'api_calls' => $records->where('metric_type', 'api_calls')->sum('quantity'),
            'total_cost' => $records->sum('total_cost'),
        ];
    }

    #[Computed]
    public function invoices()
    {
        return Invoice::where('organization_id', $this->organizationId)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();
    }

    public function render()
    {
        return view('livewire.billing.billing-dashboard');
    }
}
