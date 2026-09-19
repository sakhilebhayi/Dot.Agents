<?php

namespace App\Policies;

use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Models\User;

class AgentSkillPolicy
{
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    /**
     * Platform catalog skills (organization_id null) are visible to anyone.
     * A custom skill is visible only within the organization that owns it.
     */
    public function view(User $user, AgentSkill $skill): bool
    {
        if ($skill->organization_id === null) {
            return true;
        }

        return $user->organizations()->where('organizations.id', $skill->organization_id)->exists();
    }

    /**
     * Any owner/admin of the SPECIFIC organization may create a custom skill
     * for it. Platform admins additionally manage the shared catalog itself.
     */
    public function create(User $user, int $organizationId): bool
    {
        return $user->hasRole('admin') || $user->organizations()
            ->where('organizations.id', $organizationId)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    /**
     * A platform admin may edit any skill. An org owner/admin may edit only
     * their own org's custom skills -- never the shared platform catalog,
     * and never another org's custom skill.
     */
    public function update(User $user, AgentSkill $skill): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $skill->organization_id !== null
            && $user->organizations()
                ->where('organizations.id', $skill->organization_id)
                ->wherePivotIn('role', ['owner', 'admin'])
                ->exists();
    }

    /**
     * A platform admin may delete any skill. An org owner/admin may delete
     * only their own org's custom skills -- the shared platform catalog is
     * otherwise platform-admin-only, matching the pre-existing behavior.
     */
    public function delete(User $user, AgentSkill $skill): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $skill->organization_id !== null
            && $user->organizations()
                ->where('organizations.id', $skill->organization_id)
                ->wherePivotIn('role', ['owner', 'admin'])
                ->exists();
    }

    public function forceDelete(User $user, AgentSkill $skill): bool
    {
        return false;
    }

    /**
     * Authorize skill execution: user must belong to the deployment's org,
     * the deployment must be active, and the skill itself must actually be
     * available to that org (platform-wide, or that org's own custom skill
     * — never another org's private custom skill).
     */
    public function execute(User $user, AgentSkill $skill, AgentDeployment $deployment): bool
    {
        if ($deployment->status !== 'active') {
            return false;
        }

        if ($skill->organization_id !== null && $skill->organization_id !== $deployment->organization_id) {
            return false;
        }

        return $user->organizations()
            ->where('organizations.id', $deployment->organization_id)
            ->exists();
    }
}
