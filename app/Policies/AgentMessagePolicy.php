<?php

namespace App\Policies;

use App\Models\AgentMessage;
use App\Models\User;

class AgentMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, AgentMessage $message): bool
    {
        return $user->organizations()->where('organizations.id', $message->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return auth()->check();
    }

    public function update(User $user, AgentMessage $message): bool
    {
        return false; // Messages are immutable
    }

    public function delete(User $user, AgentMessage $message): bool
    {
        return $user->canInOrganization('agents.manage', $message->organization_id);
    }
}
