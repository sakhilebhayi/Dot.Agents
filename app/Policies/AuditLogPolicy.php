<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /** Org admins and compliance officers can list audit logs. */
    public function viewAny(User $user): bool
    {
        return $user->canInOrganization('audit.view') || $user->hasAnyRole(['owner', 'admin', 'compliance_officer', 'platform_admin']);
    }

    /** Only org members with elevated roles can view audit log entries. */
    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->canInOrganization('audit.view', $auditLog->organization_id);
    }

    /** Audit logs are system-generated — no direct creation. */
    public function create(User $user): bool
    {
        return false;
    }

    /** Audit logs are immutable. */
    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    /** Audit logs cannot be deleted (compliance requirement). */
    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
