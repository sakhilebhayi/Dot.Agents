<?php

namespace App\Actions\Governance;

use App\DTOs\Governance\DelegateApprovalData;
use App\Models\AgentApproval;
use App\Services\Approvals\ApprovalDesk;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Hands a decision to someone else who is allowed to make it. */
class DelegateApprovalAction
{
    public function __construct(private readonly ApprovalDesk $desk) {}

    public function execute(AgentApproval $approval, DelegateApprovalData $data): AgentApproval
    {
        Gate::forUser($data->actor)->authorize('review', $approval);

        if ($approval->status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'This has already been answered.']);
        }

        if (! $data->delegate->canInOrganization('approvals.review', $approval->organization_id)) {
            throw ValidationException::withMessages(['delegate' => 'That person cannot review approvals in this business.']);
        }

        $approval->update(['requested_from' => $data->delegate->id]);
        $this->desk->record($approval, $data->actor, 'delegated', trim("To {$data->delegate->name}. {$data->note}"));

        return $approval;
    }
}
