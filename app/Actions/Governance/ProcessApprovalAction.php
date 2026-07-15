<?php

namespace App\Actions\Governance;

use App\Actions\Concerns\LogsActionErrors;
use App\DTOs\Governance\ProcessApprovalData;
use App\Events\ApprovalProcessed;
use App\Models\AgentApproval;
use App\Models\DecisionLog;
use App\Services\Governance\AuditService;
use App\Services\Governance\PredictionAccuracyTrackingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProcessApprovalAction
{
    use LogsActionErrors;

    public function __construct(
        private readonly AuditService $auditService,
        private readonly PredictionAccuracyTrackingService $predictionAccuracy,
    ) {}

    /**
     * Process a human approval decision on a pending AgentApproval.
     *
     * Validates that the approval is still pending and unexpired, persists the
     * decision, fires ApprovalProcessed so the originating agent task can resume,
     * and records a PredictionAccuracy entry to track the governance model quality.
     *
     * @param  AgentApproval  $approval  The pending approval to process.
     * @param  ProcessApprovalData  $data  DTO carrying the decision and reviewer notes.
     * @return AgentApproval The updated approval record.
     *
     * @throws \RuntimeException When approval is not in 'pending' state or has expired.
     * @throws AuthorizationException When actor lacks 'review' permission.
     */
    public function execute(AgentApproval $approval, ProcessApprovalData $data): AgentApproval
    {
        Gate::authorize('review', $approval);

        if ($approval->status !== 'pending') {
            throw new \RuntimeException("Approval #{$approval->id} is already {$approval->status}.");
        }

        if ($approval->isExpired()) {
            throw new \RuntimeException("Approval #{$approval->id} has expired.");
        }

        $approval->update([
            'status' => $data->decision,
            'reviewer_notes' => $data->reviewerNotes,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        if ($approval->task_id) {
            $newTaskStatus = match ($data->decision) {
                'approved' => 'in_progress',
                'rejected' => 'failed',
                'escalated' => 'pending_escalation',
                default => 'pending',
            };
            $approval->task?->update(['status' => $newTaskStatus]);

            $this->recordDecisionOutcome($approval->task_id, $data->decision);
        }

        $this->auditService->logUserAction(
            event: "approval.{$data->decision}",
            description: "Approval #{$approval->id} {$data->decision} by reviewer",
            subject: $approval,
            metadata: ['notes' => $data->reviewerNotes],
        );

        event(new ApprovalProcessed($approval));

        return $approval->refresh();
    }

    private function recordDecisionOutcome(int $taskId, string $approvalDecision): void
    {
        $finalOutcome = match ($approvalDecision) {
            'approved' => 'implemented',
            'rejected' => 'rejected',
            'escalated' => 'escalated',
            default => null,
        };

        if ($finalOutcome === null) {
            return;
        }

        $decisionLog = DecisionLog::where('task_id', $taskId)
            ->orderByDesc('created_at')
            ->first();

        if ($decisionLog && $decisionLog->final_outcome === null) {
            $decisionLog->update([
                'final_outcome' => $finalOutcome,
                'human_verdict' => $approvalDecision === 'approved' ? 'correct' : 'incorrect',
            ]);

            $this->predictionAccuracy->recordOutcome($decisionLog);
        }
    }
}
