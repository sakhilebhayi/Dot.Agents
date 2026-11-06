<?php

namespace App\Livewire;

use App\Actions\Billing\SaveUsageBudgetAction;
use App\DTOs\Billing\SaveUsageBudgetData;
use App\Models\KnowledgeChunk;
use App\Models\UsageBudget;
use App\Services\Insights\BudgetWatch;
use App\Services\Insights\RunMetrics;
use App\Support\TenantContext;
use Livewire\Attributes\Computed;
use Livewire\Component;

class UsageOverview extends Component
{
    public const READ_ONLY_ACTIONS = ['setDays'];

    public int $days = 30;

    public ?float $limit = null;

    public int $warnAt = 80;

    public bool $stopAtLimit = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->canInOrganization('usage.view', TenantContext::id()), 403);

        $budget = $this->budget;
        $this->limit = $budget ? (float) $budget->monthly_limit : null;
        $this->warnAt = $budget->warn_at_percent ?? 80;
        $this->stopAtLimit = $budget->stop_at_limit ?? true;
    }

    #[Computed]
    public function budget(): ?UsageBudget
    {
        return UsageBudget::where('organization_id', TenantContext::id())->first();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function summary(): array
    {
        return app(RunMetrics::class)->summary(TenantContext::id(), now()->subDays($this->days));
    }

    #[Computed]
    public function agents()
    {
        return app(RunMetrics::class)->byAgent(TenantContext::id(), now()->subDays($this->days));
    }

    #[Computed]
    public function models()
    {
        return app(RunMetrics::class)->byModel(TenantContext::id(), now()->subDays($this->days));
    }

    #[Computed]
    public function spent(): float
    {
        return app(BudgetWatch::class)->spentThisMonth(TenantContext::id());
    }

    #[Computed]
    public function passages(): int
    {
        return KnowledgeChunk::where('organization_id', TenantContext::id())->count();
    }

    public function setDays(int $days): void
    {
        $this->days = in_array($days, [7, 30, 90], true) ? $days : 30;
        unset($this->summary, $this->agents, $this->models);
    }

    public function saveBudget(SaveUsageBudgetAction $action): void
    {
        $this->validate(['limit' => 'required|numeric|min:1', 'warnAt' => 'required|integer|between:1,100']);

        $action->execute(TenantContext::id(), new SaveUsageBudgetData(auth()->user(), (float) $this->limit, $this->warnAt, $this->stopAtLimit));

        unset($this->budget);
        session()->flash('saved', 'Budget saved.');
    }

    public function render()
    {
        return view('livewire.usage-overview');
    }
}
