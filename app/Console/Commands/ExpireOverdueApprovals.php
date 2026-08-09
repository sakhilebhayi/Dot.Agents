<?php

namespace App\Console\Commands;

use App\Models\AgentApproval;
use App\Models\AgentSkillApproval;
use Illuminate\Console\Command;

/**
 * Expires pending approvals (both AgentApproval and AgentSkillApproval) whose
 * expires_at deadline has passed. Pure status bookkeeping -- no task or
 * deployment side effect, no notification. This is the command
 * routes/console.php has scheduled every 30 minutes all along; it just
 * didn't exist as a class until now.
 */
class ExpireOverdueApprovals extends Command
{
    protected $signature = 'approvals:expire-overdue';

    protected $description = 'Mark pending agent/skill approvals past their expiry deadline as expired.';

    public function handle(): int
    {
        $expiredApprovals = AgentApproval::withoutGlobalScope('organization')
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $expiredSkillApprovals = AgentSkillApproval::withoutGlobalScope('organization')
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$expiredApprovals} agent approval(s) and {$expiredSkillApprovals} skill approval(s).");

        return self::SUCCESS;
    }
}
