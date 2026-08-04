<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\ExportAuditLogAction;
use App\DTOs\Governance\AuditLogExportParams;
use App\Models\AgentDeployment;
use App\Models\AuditLog;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogViewer extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterCategory = '';

    public string $filterRisk = '';

    public string $filterAgent = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $showFlagged = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function logs()
    {
        // No explicit organization_id filter needed: AuditLog's
        // HasOrganizationScope trait applies it automatically from the session.
        return AuditLog::when($this->search, fn ($q) => $q->where('description', 'like', "%{$this->search}%")
                ->orWhere('event', 'like', "%{$this->search}%")
            )
            ->when($this->filterCategory, fn ($q) => $q->where('event_category', $this->filterCategory))
            ->when($this->filterRisk, fn ($q) => $q->where('risk_level', $this->filterRisk))
            ->when($this->filterAgent, fn ($q) => $q->where('agent_deployment_id', $this->filterAgent))
            ->when($this->showFlagged, fn ($q) => $q->where('flagged', true))
            ->when($this->dateFrom, fn ($q) => $q->where('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->where('created_at', '<=', $this->dateTo.' 23:59:59'))
            ->with(['user', 'deployment.agent'])
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    #[Computed]
    public function deployments()
    {
        // No explicit organization_id filter needed: AgentDeployment's
        // HasOrganizationScope trait applies it automatically from the session.
        return AgentDeployment::with('agent')->get();
    }

    #[Computed]
    public function flaggedCount(): int
    {
        // No explicit organization_id filter needed: AuditLog's
        // HasOrganizationScope trait applies it automatically from the session.
        return AuditLog::where('flagged', true)->count();
    }

    public function render()
    {
        return view('livewire.governance.audit-log-viewer');
    }

    /**
     * Trigger a streamed CSV/JSON download of the current filtered audit log.
     * The response is returned via a redirect to a temporary signed URL served
     * by the ExportAuditLogAction; Livewire cannot stream binary downloads directly.
     */
    public function export(string $format = 'csv'): StreamedResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $params = new AuditLogExportParams(
            organizationId: session('current_organization_id'),
            format: in_array($format, ['csv', 'json']) ? $format : 'csv',
            fromDate: $this->dateFrom ?: null,
            toDate: $this->dateTo ?: null,
            eventCategory: $this->filterCategory ?: null,
            riskLevel: $this->filterRisk ?: null,
        );

        return app(ExportAuditLogAction::class)->execute($params);
    }
}
