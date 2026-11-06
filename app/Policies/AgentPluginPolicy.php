<?php

namespace App\Policies;

use App\Models\AgentPlugin;
use App\Models\User;

class AgentPluginPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Public marketplace
    }

    public function view(User $user, AgentPlugin $plugin): bool
    {
        // Platform-wide plugins are visible to all; org plugins only to that org
        if (is_null($plugin->organization_id)) {
            return true;
        }

        return $user->organizations()->where('organizations.id', $plugin->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function update(User $user, AgentPlugin $plugin): bool
    {
        if (is_null($plugin->organization_id)) {
            return $user->hasRole('super-admin');
        }

        return $user->canInOrganization('tools.manage', $plugin->organization_id);
    }

    public function delete(User $user, AgentPlugin $plugin): bool
    {
        if (is_null($plugin->organization_id)) {
            return $user->hasRole('super-admin');
        }

        return $user->canInOrganization('tools.manage', $plugin->organization_id);
    }
}
