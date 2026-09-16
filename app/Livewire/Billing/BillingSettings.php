<?php

namespace App\Livewire\Billing;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Livewire\Attributes\Computed;
use Livewire\Component;

class BillingSettings extends Component
{
    #[Computed]
    public function organizationId(): ?int
    {
        return session('current_organization_id');
    }

    #[Computed]
    public function organization(): ?Organization
    {
        return Organization::find($this->organizationId);
    }

    #[Computed]
    public function subscription(): ?OrganizationSubscription
    {
        // No explicit organization_id filter needed: OrganizationSubscription's
        // HasOrganizationScope trait applies it automatically from the session.
        return OrganizationSubscription::with('plan')
            ->where('status', 'active')
            ->orderByDesc('current_period_start')
            ->first();
    }

    #[Computed]
    public function invoices()
    {
        // No explicit organization_id filter needed: Invoice's
        // HasOrganizationScope trait applies it automatically from the session.
        return Invoice::orderByDesc('invoice_date')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.billing.billing-settings');
    }
}
