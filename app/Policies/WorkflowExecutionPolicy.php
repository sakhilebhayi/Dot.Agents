<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowExecution;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkflowExecutionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->organizations()->where('organizations.id', session('current_organization_id'))->exists();
    }

    public function view(User $user, WorkflowExecution $execution): bool
    {
        return $execution->organization_id === (int) session('current_organization_id');
    }

    public function create(User $user): bool
    {
        // Executions are triggered by the system or by users with deploy rights
        $orgId = (int) session('current_organization_id');

        return $user->canInOrganization('agents.operate', $orgId);
    }

    public function update(User $user, WorkflowExecution $execution): bool
    {
        // Executions are system-managed; only admins may manually update status
        $orgId = (int) session('current_organization_id');

        return $execution->organization_id === $orgId
            && $user->canInOrganization('workflows.manage', $orgId);
    }

    public function delete(User $user, WorkflowExecution $execution): bool
    {
        return false; // Execution records are audit trail — never deleted directly
    }
}
