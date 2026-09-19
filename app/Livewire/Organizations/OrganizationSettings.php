<?php

namespace App\Livewire\Organizations;

use App\Actions\Organizations\UpdateOrganizationSettingsAction;
use App\DTOs\Organizations\UpdateOrganizationSettingsData;
use App\Models\Organization;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class OrganizationSettings extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public ?string $domain = null;

    #[Validate('nullable|string|max:100')]
    public ?string $industry = null;

    #[Validate('nullable|string|max:50')]
    public ?string $size = null;

    #[Validate('nullable|string|max:10')]
    public ?string $country = null;

    #[Validate('nullable|string|max:50')]
    public ?string $timezone = null;

    #[Validate('nullable|string|max:10')]
    public ?string $currency = null;

    public bool $saved = false;

    public string $tab = 'general';

    public function mount(): void
    {
        $org = $this->currentOrganization;
        $this->name = $org->name;
        $this->domain = $org->domain;
        $this->industry = $org->industry;
        $this->size = $org->size;
        $this->country = $org->country;
        $this->timezone = $org->timezone;
        $this->currency = $org->currency ?? 'USD';
    }

    #[Computed]
    public function currentOrganization(): Organization
    {
        $orgId = session('current_organization_id');
        if (! $orgId) {
            abort(403, 'No active organization context.');
        }

        return Organization::findOrFail($orgId);
    }

    public function save(UpdateOrganizationSettingsAction $action): void
    {
        $this->validate();

        $action->execute($this->currentOrganization, UpdateOrganizationSettingsData::fromArray([
            'name' => $this->name,
            'domain' => $this->domain,
            'industry' => $this->industry,
            'size' => $this->size,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
        ]));

        $this->saved = true;
        $this->dispatch('settings-saved');

        // Auto-clear confirmation after 3 seconds
        $this->js('setTimeout(() => { $wire.saved = false }, 3000)');

        unset($this->currentOrganization);
    }

    public function render()
    {
        return view('livewire.organizations.organization-settings');
    }
}
