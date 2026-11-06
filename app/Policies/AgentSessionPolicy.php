<?php

namespace App\Policies;

use App\Models\AgentSession;
use App\Models\User;

class AgentSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, AgentSession $session): bool
    {
        return $user->organizations()
            ->where('organizations.id', $session->organization_id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return auth()->check();
    }

    public function update(User $user, AgentSession $session): bool
    {
        return $user->canInOrganization('agents.manage', $session->organization_id);
    }

    public function delete(User $user, AgentSession $session): bool
    {
        return $user->canInOrganization('agents.manage', $session->organization_id);
    }

    public function forceDelete(User $user, AgentSession $session): bool
    {
        return false;
    }
}
