<?php

namespace App\Actions\Governance;

use App\DTOs\Governance\ProcessRetentionPurgeData;
use App\Events\RetentionPurgeProcessed;
use App\Models\RetentionPurgeProposal;
use App\Services\Governance\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProcessRetentionPurgeAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Process a human decision on a pending RetentionPurgeProposal.
     *
     * On approval, this is the only place any of the configured models'
     * rows are actually deleted -- via that model's own already-defined
     * prunable() query and Laravel's real MassPrunable::pruneAll().
     *
     * @throws \RuntimeException When the proposal is not 'pending'.
     * @throws AuthorizationException When the actor lacks 'review' permission.
     */
    public function execute(RetentionPurgeProposal $proposal, ProcessRetentionPurgeData $data): RetentionPurgeProposal
    {
        Gate::authorize('review', $proposal);

        if ($proposal->status !== 'pending') {
            throw new \RuntimeException("Retention purge proposal #{$proposal->id} is already {$proposal->status}.");
        }

        $deletedCount = null;

        if ($data->decision === 'approved') {
            $modelClass = $proposal->model_class;
            $deletedCount = (new $modelClass)->pruneAll();
        }

        $proposal->update([
            'status' => $data->decision,
            'reviewer_notes' => $data->reviewerNotes,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'deleted_count' => $deletedCount,
        ]);

        $this->auditService->logUserAction(
            event: "retention_purge.{$data->decision}",
            description: "Retention purge proposal #{$proposal->id} ({$proposal->model_class}) {$data->decision}",
            data: ['deleted_count' => $deletedCount],
            subject: $proposal,
        );

        event(new RetentionPurgeProcessed($proposal));

        return $proposal->refresh();
    }
}
