<?php

namespace App\Policies;

use App\Models\DecisionLog;
use App\Models\User;

class DecisionLogPolicy
{
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, DecisionLog $decisionLog): bool
    {
        return $user->organizations()->where('organizations.id', $decisionLog->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return auth()->check();
    }

    /** Human review annotation on decisions is admin-only. */
    public function update(User $user, DecisionLog $decisionLog): bool
    {
        return $user->canInOrganization('approvals.review', $decisionLog->organization_id);
    }

    /** Decision logs cannot be deleted (governance requirement). */
    public function delete(User $user, DecisionLog $decisionLog): bool
    {
        return false;
    }
}
