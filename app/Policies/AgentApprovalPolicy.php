<?php

namespace App\Policies;

use App\Models\AgentApproval;
use App\Models\User;

class AgentApprovalPolicy
{
    public function view(User $user, AgentApproval $approval): bool
    {
        $orgId = $approval->deployment?->organization_id;

        return $user->organizations()->where('organizations.id', $orgId)->exists();
    }

    public function review(User $user, AgentApproval $approval): bool
    {
        $orgId = $approval->deployment?->organization_id;

        return $user->canInOrganization('approvals.review', $orgId);
    }
}
