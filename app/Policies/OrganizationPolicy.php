<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->organizations()->where('organizations.id', $organization->id)->exists();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    public function manageBilling(User $user, Organization $organization): bool
    {
        return $user->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }
}
