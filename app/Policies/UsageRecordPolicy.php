<?php

namespace App\Policies;

use App\Models\UsageRecord;
use App\Models\User;

class UsageRecordPolicy
{
    /** Org members can view usage records for their organization. */
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, UsageRecord $record): bool
    {
        return $user->organizations()
            ->where('organizations.id', $record->organization_id)
            ->exists();
    }

    /** Usage records are system-generated — no user creation. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, UsageRecord $record): bool
    {
        return false;
    }

    /** Only platform admins can delete usage records. */
    public function delete(User $user, UsageRecord $record): bool
    {
        return $user->hasRole('platform_admin');
    }

    public function forceDelete(User $user, UsageRecord $record): bool
    {
        return false;
    }
}
