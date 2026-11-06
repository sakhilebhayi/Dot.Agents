<?php

namespace App\Policies;

use App\Models\AgentTask;
use App\Models\User;

class AgentTaskPolicy
{
    /**
     * Any authenticated org member can view tasks belonging to their organization.
     */
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    /**
     * User can view a task only if they belong to the task's organization.
     */
    public function view(User $user, AgentTask $agentTask): bool
    {
        return $user->organizations()
            ->where('organizations.id', $agentTask->organization_id)
            ->exists();
    }

    /**
     * Org members can create tasks within their organization.
     */
    public function create(User $user): bool
    {
        return auth()->check();
    }

    /**
     * Only org admins/owners can update tasks (e.g. reassign, change priority).
     */
    public function update(User $user, AgentTask $agentTask): bool
    {
        return $user->canInOrganization('agents.manage', $agentTask->organization_id);
    }

    /**
     * Only org owners can delete tasks.
     */
    public function delete(User $user, AgentTask $agentTask): bool
    {
        return $user->canInOrganization('agents.manage', $agentTask->organization_id);
    }

    /**
     * Restore follows the same rules as delete.
     */
    public function restore(User $user, AgentTask $agentTask): bool
    {
        return $this->delete($user, $agentTask);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AgentTask $agentTask): bool
    {
        return false;
    }

    /**
     * User can rate a task if they belong to the org and the task is completed.
     * A task can only be rated once (checked by RateAgentTaskAction, not here).
     */
    public function rate(User $user, AgentTask $agentTask): bool
    {
        return $agentTask->status === 'completed'
            && $user->organizations()
                ->where('organizations.id', $agentTask->organization_id)
                ->exists();
    }
}
