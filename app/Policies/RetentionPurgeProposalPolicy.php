<?php

namespace App\Policies;

use App\Models\RetentionPurgeProposal;
use App\Models\User;

class RetentionPurgeProposalPolicy
{
    /**
     * Retention purges are cross-organization, compliance-adjacent
     * infrastructure decisions -- gated by the same platform_admin role
     * AuditLogPolicy already uses to restrict who can even read audit logs.
     * No per-organization admin/manager role applies here; this is
     * deliberately narrower than that.
     */
    public function review(User $user, RetentionPurgeProposal $proposal): bool
    {
        return $user->hasAnyRole(['platform_admin']);
    }
}
