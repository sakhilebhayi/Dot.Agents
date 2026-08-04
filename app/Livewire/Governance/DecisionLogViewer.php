<?php

namespace App\Livewire\Governance;

use App\Models\AgentDeployment;
use App\Models\DecisionLog;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class DecisionLogViewer extends Component
{
    use WithPagination;

    public string $filterRisk = '';

    public string $filterDeployment = '';

    public string $filterReviewRequired = '';

    public string $timeframe = '30d';

    public ?int $viewingId = null;

    #[Computed]
    public function decisions()
    {
        $since = match ($this->timeframe) {
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            default => now()->subDays(30),
        };

        // No explicit organization_id filter needed: DecisionLog's
        // HasOrganizationScope trait applies it automatically from the session.
        return DecisionLog::where('created_at', '>=', $since)
            ->when($this->filterRisk, fn ($q) => $q->where('risk_score', $this->filterRisk === 'high' ? '>=' : ($this->filterRisk === 'medium' ? '>=' : '<'), $this->filterRisk === 'high' ? 70 : ($this->filterRisk === 'medium' ? 40 : 40)))
            ->when($this->filterDeployment, fn ($q) => $q->where('agent_deployment_id', $this->filterDeployment))
            ->when($this->filterReviewRequired === '1', fn ($q) => $q->where('requires_human_review', true))
            ->when($this->filterReviewRequired === '0', fn ($q) => $q->where('requires_human_review', false))
            ->with(['deployment.agent'])
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    #[Computed]
    public function deployments()
    {
        // No explicit organization_id filter needed: AgentDeployment's
        // HasOrganizationScope trait applies it automatically from the session.
        return AgentDeployment::with('agent')
            ->orderBy('name')
            ->get(['id', 'name', 'agent_id']);
    }

    #[Computed]
    public function viewingDecision(): ?DecisionLog
    {
        return $this->viewingId ? DecisionLog::with(['deployment.agent'])->find($this->viewingId) : null;
    }

    public function view(int $id): void
    {
        $this->viewingId = $id;
        unset($this->viewingDecision);
    }

    public function closeDetail(): void
    {
        $this->viewingId = null;
    }

    public function updatedFilterRisk(): void
    {
        $this->resetPage();
        unset($this->decisions);
    }

    public function updatedFilterDeployment(): void
    {
        $this->resetPage();
        unset($this->decisions);
    }

    public function updatedTimeframe(): void
    {
        $this->resetPage();
        unset($this->decisions);
    }

    public function render()
    {
        return view('livewire.governance.decision-log-viewer');
    }
}
