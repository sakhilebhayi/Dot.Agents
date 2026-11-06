<?php

namespace App\Policies;

use App\Models\SocialLead;
use App\Models\User;

class SocialLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return auth()->check();
    }

    public function view(User $user, SocialLead $lead): bool
    {
        return $user->organizations()->where('organizations.id', $lead->organization_id)->exists();
    }

    public function create(User $user, int $organizationId): bool
    {
        return $user->organizations()->where('organizations.id', $organizationId)->exists();
    }

    public function update(User $user, SocialLead $lead): bool
    {
        return $user->organizations()
            ->where('organizations.id', $lead->organization_id)
            ->wherePivotIn('role', ['owner', 'admin', 'operator', 'member'])
            ->exists();
    }

    public function qualify(User $user, SocialLead $lead): bool
    {
        return $user->organizations()
            ->where('organizations.id', $lead->organization_id)
            ->exists();
    }
}
