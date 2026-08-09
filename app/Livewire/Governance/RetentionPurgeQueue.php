<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\ProcessRetentionPurgeAction;
use App\DTOs\Governance\ProcessRetentionPurgeData;
use App\Models\RetentionPurgeProposal;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RetentionPurgeQueue extends Component
{
    public string $reviewerNotes = '';

    #[Computed]
    public function proposals()
    {
        return RetentionPurgeProposal::where('status', 'pending')
            ->orderBy('created_at')
            ->paginate(15);
    }

    #[Computed]
    public function pendingCount(): int
    {
        return RetentionPurgeProposal::where('status', 'pending')->count();
    }

    public function approve(int $id): void
    {
        $proposal = RetentionPurgeProposal::findOrFail($id);

        // SECURITY: $id is a Livewire method argument, fully attacker-controlled.
        abort_unless(auth()->user()->can('review', $proposal), 403);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved', $this->reviewerNotes ?: null),
        );

        $this->reviewerNotes = '';
        unset($this->proposals, $this->pendingCount);
        session()->flash('success', 'Retention purge approved.');
    }

    public function reject(int $id): void
    {
        $proposal = RetentionPurgeProposal::findOrFail($id);

        abort_unless(auth()->user()->can('review', $proposal), 403);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'rejected', $this->reviewerNotes ?: null),
        );

        $this->reviewerNotes = '';
        unset($this->proposals, $this->pendingCount);
        session()->flash('success', 'Retention purge rejected.');
    }

    public function render()
    {
        return view('livewire.governance.retention-purge-queue');
    }
}
