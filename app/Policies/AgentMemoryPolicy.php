<?php

namespace App\Policies;

use App\Models\AgentMemory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * AgentMemoryPolicy — governs access to AI agent memory records.
 *
 * Agent memory may contain sensitive context from user interactions.
 * Only org members may view their own org's agent memory.
 * Direct deletion is restricted to admins to prevent memory poisoning.
 */
class AgentMemoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->organizations()->where('organizations.id', session('current_organization_id'))->exists();
    }

    public function view(User $user, AgentMemory $memory): bool
    {
        return $memory->organization_id === (int) session('current_organization_id');
    }

    public function create(User $user): bool
    {
        // Memory is created by the system (agents), not directly by users
        return false;
    }

    public function update(User $user, AgentMemory $memory): bool
    {
        // Memory updates are system-only
        return false;
    }

    public function delete(User $user, AgentMemory $memory): bool
    {
        $orgId = (int) session('current_organization_id');

        // Only org admins/owners can purge agent memory
        return $memory->organization_id === $orgId
            && $user->canInOrganization('memory.govern', $orgId);
    }
}
