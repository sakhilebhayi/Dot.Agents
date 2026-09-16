<?php

namespace App\Livewire\Marketplace;

use App\Livewire\Concerns\ManagesAgentDeploy;
use App\Livewire\Forms\DeployAgentForm;
use App\Models\Agent;
use App\Models\AgentCategory;
use App\Models\AgentDepartment;
use App\Models\Department;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
class AgentMarketplace extends Component
{
    use ManagesAgentDeploy, WithPagination;

    // ── Search & Sort ────────────────────────────────────────────────
    public string $search = '';

    public string $sortBy = 'featured';

    // ── Filters ──────────────────────────────────────────────────────
    public string $selectedDepartment = '';

    public string $selectedCategory = '';

    public string $selectedType = '';

    public string $statusFilter = 'active';

    public string $costTier = '';

    public string $selectedSkill = '';

    public int $trustScoreMin = 0;

    // ── Modals ───────────────────────────────────────────────────────
    public ?Agent $previewAgent = null;

    public DeployAgentForm $deployForm;

    // ── Filter change hooks (all reset pagination) ────────────────────
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedDepartment(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedType(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCostTier(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedSkill(): void
    {
        $this->resetPage();
    }

    public function updatedTrustScoreMin(): void
    {
        $this->resetPage();
    }

    // ── Explicit setters (used from blade for safer x-data-free clicks) ──
    public function setDepartment(string $slug): void
    {
        $this->selectedDepartment = $this->selectedDepartment === $slug ? '' : $slug;
        $this->resetPage();
    }

    public function setSkill(string $skill): void
    {
        $this->selectedSkill = $this->selectedSkill === $skill ? '' : $skill;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search', 'selectedDepartment', 'selectedCategory',
            'selectedType', 'statusFilter', 'costTier',
            'selectedSkill', 'trustScoreMin', 'sortBy',
        ]);
        $this->statusFilter = 'active';
        $this->resetPage();
    }

    // ── Computed queries ─────────────────────────────────────────────
    #[Computed]
    public function agents()
    {
        $status = $this->statusFilter ?: 'active';

        return Agent::query()
            ->where('status', $status)
            ->when($this->search, fn ($q) => $q->where(function ($sq) {
                $sq->where('name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%")
                    ->orWhere('tagline', 'like', "%{$this->search}%");
            }))
            ->when($this->selectedDepartment, fn ($q) => $q->whereHas(
                'agentDepartment', fn ($dq) => $dq->where('slug', $this->selectedDepartment)
            ))
            ->when($this->selectedCategory, fn ($q) => $q->whereHas(
                'category', fn ($cq) => $cq->where('slug', $this->selectedCategory)
            ))
            ->when($this->selectedType, fn ($q) => $q->where('agent_type', $this->selectedType))
            ->when($this->costTier, fn ($q) => $q->where('cost_tier', $this->costTier))
            ->when($this->selectedSkill, fn ($q) => $q->whereJsonContains('skills', $this->selectedSkill))
            ->when($this->trustScoreMin > 0, fn ($q) => $q->where('trust_score', '>=', $this->trustScoreMin))
            ->when($this->sortBy === 'featured', fn ($q) => $q->orderByDesc('is_featured')->orderByDesc('avg_rating'))
            ->when($this->sortBy === 'rating', fn ($q) => $q->orderByDesc('avg_rating'))
            ->when($this->sortBy === 'popular', fn ($q) => $q->orderByDesc('total_deployments'))
            ->when($this->sortBy === 'trust', fn ($q) => $q->orderByDesc('trust_score'))
            ->when($this->sortBy === 'performance', fn ($q) => $q->orderByDesc('performance_score'))
            ->when($this->sortBy === 'price_asc', fn ($q) => $q->orderBy('base_price'))
            ->when($this->sortBy === 'price_desc', fn ($q) => $q->orderByDesc('base_price'))
            ->with(['agentDepartment', 'category'])
            ->paginate(12);
    }

    #[Computed]
    public function departments()
    {
        return Cache::remember('marketplace_departments', 600, fn () => AgentDepartment::where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    #[Computed]
    public function orgDepartments()
    {
        // Department's HasOrganizationScope trait scopes this to the
        // current organization automatically; not cached because it is
        // per-organization, unlike the marketplace catalog above.
        return Department::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function categories()
    {
        return Cache::remember('marketplace_categories', 600, fn () => AgentCategory::where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    #[Computed]
    public function availableSkills(): array
    {
        // Collect all unique skill strings from active agents
        return Cache::remember('marketplace_available_skills', 600, function () {
            $rows = Agent::where('status', 'active')
                ->whereNotNull('skills')
                ->pluck('skills');

            return $rows->flatten()->unique()->sort()->values()->toArray();
        });
    }

    // ── Preview modal ────────────────────────────────────────────────
    public function openPreview(int $agentId): void
    {
        $this->previewAgent = Agent::with([
            'agentDepartment',
            'category',
            'reviews' => fn ($q) => $q->latest()->take(3),
        ])->find($agentId);
    }

    public function closePreview(): void
    {
        $this->previewAgent = null;
    }

    // ── Deploy modal — handled by ManagesAgentDeploy trait ───────────

    public function placeholder()
    {
        return view('livewire.marketplace.agent-marketplace-placeholder');
    }

    public function render()
    {
        return view('livewire.marketplace.agent-marketplace');
    }
}
