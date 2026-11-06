<?php

namespace App\Livewire\Marketplace;

use App\Actions\Agents\SaveBusinessTemplateAction;
use App\Models\BusinessTemplate;
use App\Services\Tools\ToolCatalogue;
use App\Support\TenantContext;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Working starting points and the tools there are. Nothing here is ranked by popularity: there is nothing true to rank by. */
class TemplateLibrary extends Component
{
    public const READ_ONLY_ACTIONS = ['setTab'];

    #[Url(except: 'official')]
    public string $tab = 'official';

    #[Url(as: 'category', except: '')]
    public string $category = '';

    /** @return array<string, array<string, mixed>> */
    #[Computed]
    public function templates(): array
    {
        return collect(config('agent_templates'))->when($this->category, fn ($all) => $all->where('category', $this->category))->all();
    }

    /** @return list<string> */
    #[Computed]
    public function categories(): array
    {
        return collect(config('agent_templates'))->pluck('category')->unique()->sort()->values()->all();
    }

    #[Computed]
    public function own()
    {
        return BusinessTemplate::latestVersions()->where('organization_id', TenantContext::id())->orderBy('name')->get();
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function tools(): array
    {
        $catalogue = app(ToolCatalogue::class);
        $organization = (int) TenantContext::id();

        return collect($catalogue->all($organization))->map(fn ($tool) => [
            'tool' => $tool->definition(),
            'missing' => $catalogue->missing($organization, $tool->definition()->key),
        ])->sortBy(fn ($row) => $row['tool']->category)->values()->all();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function removeOwn(string $name, SaveBusinessTemplateAction $action): void
    {
        $action->remove(auth()->user(), (int) TenantContext::id(), $name);
        unset($this->own);
    }

    public function render()
    {
        return view('livewire.marketplace.template-library');
    }
}
