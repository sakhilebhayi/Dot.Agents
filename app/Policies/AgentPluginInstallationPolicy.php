<?php

namespace App\Policies;

use App\Models\AgentPluginInstallation;
use App\Models\User;

class AgentPluginInstallationPolicy
{
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, AgentPluginInstallation $installation): bool
    {
        return $user->organizations()
            ->where('organizations.id', $installation->organization_id)
            ->exists();
    }

    /** Only org admins/owners can install plugins. */
    public function create(User $user): bool
    {
        return $user->organizations()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    public function update(User $user, AgentPluginInstallation $installation): bool
    {
        return $user->canInOrganization('tools.manage', $installation->organization_id);
    }

    /** Only org owners can uninstall plugins. */
    public function delete(User $user, AgentPluginInstallation $installation): bool
    {
        return $user->canInOrganization('tools.manage', $installation->organization_id);
    }

    public function forceDelete(User $user, AgentPluginInstallation $installation): bool
    {
        return false;
    }
}
