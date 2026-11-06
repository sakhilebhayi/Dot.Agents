<?php

namespace App\Policies;

use App\Models\SecurityEvent;
use App\Models\User;

class SecurityEventPolicy
{
    /** Only admins and security officers can list security events. */
    public function viewAny(User $user): bool
    {
        return $user->canInOrganization('security.view') || $user->hasAnyRole(['owner', 'admin', 'security_officer', 'platform_admin']);
    }

    /** Only org members with security roles can view events for their org. */
    public function view(User $user, SecurityEvent $securityEvent): bool
    {
        return $user->canInOrganization('security.view', $securityEvent->organization_id);
    }

    /** Security events are system-generated — no direct creation. */
    public function create(User $user): bool
    {
        return false;
    }

    /** Only security officers and admins can resolve security events. */
    public function update(User $user, SecurityEvent $securityEvent): bool
    {
        return $user->canInOrganization('security.view', $securityEvent->organization_id);
    }

    /** Security events cannot be deleted (audit trail requirement). */
    public function delete(User $user, SecurityEvent $securityEvent): bool
    {
        return false;
    }
}
