<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowConnection;

class WorkflowConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, WorkflowConnection $connection): bool
    {
        return $user->organizations()->where('organizations.id', $connection->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return auth()->check();
    }

    public function update(User $user, WorkflowConnection $connection): bool
    {
        return $user->canInOrganization('workflows.manage', $connection->organization_id);
    }

    public function delete(User $user, WorkflowConnection $connection): bool
    {
        return $user->canInOrganization('workflows.manage', $connection->organization_id);
    }
}
