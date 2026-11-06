<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\DelegateApprovalAction;
use App\Actions\Governance\ProcessApprovalAction;
use App\DTOs\Governance\DelegateApprovalData;
use App\DTOs\Governance\ProcessApprovalData;
use App\Livewire\Forms\ApprovalResponseForm;
use App\Models\AgentApproval;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class ApprovalQueue extends Component
{
    use WithPagination;

    /** Actions a read-only (viewer) role may still call; every other action is refused for it. */
    public const READ_ONLY_ACTIONS = ['selectApproval', 'closeApproval', 'setTab'];

    #[Url(as: 'tab', except: 'pending')]
    public string $filterStatus = 'pending';

    #[Url(as: 'risk', except: '')]
    public string $filterRisk = '';

    #[Url(as: 'approval', except: '')]
    public string $open = '';

    public ?AgentApproval $selectedApproval = null;

    public string $reviewerNotes = '';

    public ApprovalResponseForm $responseForm;

    /** @var array<string, mixed> The values as the reviewer has them, shown when changing an action. */
    public array $values = [];

    public bool $changing = false;

    public ?int $delegateTo = null;

    public function mount(): void
    {
        if ($this->open !== '') {
            $this->selectApproval((int) (AgentApproval::where('uuid', $this->open)->value('id') ?? abort(404)));
        }
    }

    #[Computed]
    public function approvals()
    {
        // No explicit organization_id filter needed: AgentApproval's
        // HasOrganizationScope trait applies it automatically from the session.
        return AgentApproval::when($this->filterStatus === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($this->filterStatus === 'answered', fn ($q) => $q->where('status', '!=', 'pending'))
            ->when(! in_array($this->filterStatus, ['pending', 'answered', ''], true), fn ($q) => $q->where('status', $this->filterStatus))
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

    /** People this could be handed to. */
    #[Computed]
    public function reviewers()
    {
        return $this->selectedApproval?->organization->users()->whereKeyNot(auth()->id())->orderBy('name')->get()
            ->filter(fn (User $user) => $user->canInOrganization('approvals.review', $this->selectedApproval->organization_id))->values() ?? collect();
    }

    public function setTab(string $tab): void
    {
        $this->filterStatus = $tab;
        $this->closeApproval();
        $this->resetPage();
    }

    public function selectApproval(int $id): void
    {
        $approval = AgentApproval::with([
            'deployment.agent', 'task', 'requestedFrom', 'events.user', 'run',
        ])->findOrFail($id);

        // SECURITY: $id is a Livewire method argument, not a synced/checksummed
        // property — it is fully attacker-controlled. Enforce tenancy here rather
        // than relying on the query above, or another org's approval details leak.
        abort_unless(auth()->user()->can('view', $approval), 403);

        $this->selectedApproval = $approval;
        $this->open = $approval->uuid;
        $this->values = $approval->proposed_action['values'] ?? [];
        $this->changing = false;
        $this->reviewerNotes = '';
        $this->responseForm->reset();
    }

    public function closeApproval(): void
    {
        $this->selectedApproval = null;
        $this->open = '';
    }

    public function approve(): void
    {
        $this->responseForm->decision = 'approved';
        $this->processApproval('approved', $this->changing ? $this->values : null);
    }

    public function reject(): void
    {
        $this->responseForm->decision = 'rejected';
        $this->responseForm->rejection_reason = $this->responseForm->notes;
        $this->responseForm->validate();
        $this->processApproval('rejected');
    }

    public function requestChanges(): void
    {
        $this->responseForm->validate(['notes' => 'required|string|max:2000'], ['notes.required' => 'Say what should change.']);
        $this->processApproval('changes_requested');
    }

    public function escalate(): void
    {
        $this->processApproval('escalated');
    }

    public function delegate(DelegateApprovalAction $action): void
    {
        $delegate = User::find($this->delegateTo);

        if ($this->selectedApproval === null || $delegate === null) {
            return;
        }

        $action->execute($this->selectedApproval, new DelegateApprovalData(auth()->user(), $delegate, $this->responseForm->notes));

        $this->finish("Handed to {$delegate->name}.");
    }

    /** @param  array<string, mixed>|null  $edited */
    private function processApproval(string $verdict, ?array $edited = null): void
    {
        if (! $this->selectedApproval) {
            return;
        }

        try {
            app(ProcessApprovalAction::class)->execute(
                $this->selectedApproval,
                new ProcessApprovalData(
                    $this->selectedApproval->id,
                    $verdict,
                    $this->responseForm->notes ?? $this->reviewerNotes ?: null,
                    $edited,
                ),
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['approval' => $e->getMessage()]);
        }

        $this->finish('Answer recorded.');
    }

    private function finish(string $message): void
    {
        $this->closeApproval();
        $this->reviewerNotes = '';
        $this->responseForm->reset();
        unset($this->approvals, $this->pendingCount);

        $this->dispatch('approval-processed');
        session()->flash('success', $message);
    }

    public function render()
    {
        return view('livewire.governance.approval-queue');
    }
}
