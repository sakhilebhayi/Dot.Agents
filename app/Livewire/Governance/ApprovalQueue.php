<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\ProcessApprovalAction;
use App\DTOs\Governance\ProcessApprovalData;
use App\Livewire\Forms\ApprovalResponseForm;
use App\Models\AgentApproval;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ApprovalQueue extends Component
{
    use WithPagination;

    public string $filterStatus = 'pending';

    public string $filterRisk = '';

    public ?AgentApproval $selectedApproval = null;

    public string $reviewerNotes = '';

    public ApprovalResponseForm $responseForm;

    #[Computed]
    public function approvals()
    {
        // No explicit organization_id filter needed: AgentApproval's
        // HasOrganizationScope trait applies it automatically from the session.
        return AgentApproval::when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterRisk, fn ($q) => $q->where('risk_level', $this->filterRisk))
            ->with(['deployment.agent', 'task', 'requestedFrom'])
            ->orderByRaw("CASE risk_level WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderBy('created_at')
            ->paginate(15);
    }

    #[Computed]
    public function pendingCount(): int
    {
        return AgentApproval::where('organization_id', session('current_organization_id'))
            ->where('status', 'pending')
            ->count();
    }

    public function selectApproval(int $id): void
    {
        $approval = AgentApproval::with([
            'deployment.agent', 'task', 'requestedFrom',
        ])->findOrFail($id);

        // SECURITY: $id is a Livewire method argument, not a synced/checksummed
        // property — it is fully attacker-controlled. Enforce tenancy here rather
        // than relying on the query above, or another org's approval details leak.
        abort_unless(auth()->user()->can('view', $approval), 403);

        $this->selectedApproval = $approval;
        $this->reviewerNotes = '';
        $this->responseForm->reset();
    }

    public function approve(): void
    {
        $this->responseForm->decision = 'approved';
        $this->processApproval('approved');
    }

    public function reject(): void
    {
        $this->responseForm->decision = 'rejected';
        $this->responseForm->validate();
        $this->processApproval('rejected');
    }

    public function escalate(): void
    {
        $this->processApproval('escalated');
    }

    private function processApproval(string $verdict): void
    {
        if (! $this->selectedApproval) {
            return;
        }

        app(ProcessApprovalAction::class)->execute(
            $this->selectedApproval,
            new ProcessApprovalData(
                $this->selectedApproval->id,
                $verdict,
                $this->responseForm->notes ?? $this->reviewerNotes ?: null,
            ),
        );

        $this->selectedApproval = null;
        $this->reviewerNotes = '';
        $this->responseForm->reset();
        unset($this->approvals, $this->pendingCount);

        $this->dispatch('approval-processed');
        session()->flash('success', "Decision recorded: {$verdict}");
    }

    public function render()
    {
        return view('livewire.governance.approval-queue');
    }
}
