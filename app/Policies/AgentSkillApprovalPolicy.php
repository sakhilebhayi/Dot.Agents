<?php

namespace App\Policies;

use App\Models\AgentSkillApproval;
use App\Models\User;

class AgentSkillApprovalPolicy
{
    /** Anyone in the org can view their own approval requests. */
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, AgentSkillApproval $approval): bool
    {
        return $user->organizations()
            ->where('organizations.id', $approval->organization_id)
            ->exists();
    }

    /**
     * Only org owners or admins may approve or reject skill executions.
     * This gate is called for both approve and reject decisions.
     */
    public function review(User $user, AgentSkillApproval $approval): bool
    {
        return $user->canInOrganization('approvals.review', $approval->organization_id);
    }
}
