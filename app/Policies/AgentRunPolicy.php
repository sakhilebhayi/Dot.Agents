<?php

namespace App\Policies;

use App\Models\AgentRun;
use App\Models\User;

class AgentRunPolicy
{
    public function view(User $user, AgentRun $run): bool
    {
        return $user->canInOrganization('runs.view', $run->organization_id);
    }

    public function cancel(User $user, AgentRun $run): bool
    {
        return $user->canInOrganization('agents.operate', $run->organization_id);
    }
}
