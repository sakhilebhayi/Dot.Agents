<?php

namespace App\Console\Commands;

use App\Models\AgentApproval;
use App\Models\AgentSkillApproval;
use App\Services\Approvals\ApprovalDesk;
use App\Services\Approvals\ApprovalResume;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * Expires pending approvals (both AgentApproval and AgentSkillApproval) whose
 * expires_at deadline has passed. A run waiting on one is told and carries on.
 */
class ExpireOverdueApprovals extends Command
{
    protected $signature = 'approvals:expire-overdue';

    protected $description = 'Mark pending agent/skill approvals past their expiry deadline as expired.';

    public function handle(ApprovalResume $resume, ApprovalDesk $desk): int
    {
        $expiredApprovals = 0;

        // One at a time, because a run behind an approval has to hear the answer.
        TenantContext::system(fn () => AgentApproval::where('status', 'pending')->where('expires_at', '<', now())
            ->each(function (AgentApproval $approval) use ($resume, $desk, &$expiredApprovals) {
                $approval->update(['status' => 'expired']);
                $desk->record($approval, null, 'expired');
                $expiredApprovals++;

                if ($approval->agent_run_id !== null) {
                    $resume->declined($approval, 'approval_expired', 'Nobody answered in time, so this was not done.');
                }
            }));

        $expiredSkillApprovals = AgentSkillApproval::withoutGlobalScope('organization')
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$expiredApprovals} agent approval(s) and {$expiredSkillApprovals} skill approval(s).");

        return self::SUCCESS;
    }
}
