<?php

namespace App\Livewire\Concerns;

use App\Models\Organization;

/**
 * ResolvesCurrentOrganization
 *
 * OrganizationContextMiddleware only guarantees a valid, non-null
 * `current_organization_id` session value for the request it runs on. If a
 * user's membership in that organization is revoked between requests, the
 * middleware forgets the stale session value and the request continues with
 * no organization context — `session('current_organization_id')` is null for
 * the rest of that request. Every Livewire action that would otherwise read
 * or write org-scoped data must use this helper instead of reading the
 * session directly, so that scenario aborts loudly instead of crashing on a
 * null `findOrFail()` lookup or silently writing `organization_id => 0`.
 *
 * Centralises what used to be an ad-hoc `abort_if(! $orgId, 403, ...)` check
 * duplicated across several components (BillingController, ApiKeyManager,
 * ManagesAgentDeploy, OrganizationSettings, ...).
 */
trait ResolvesCurrentOrganization
{
    /**
     * Resolve the current organization ID, or null when no organization
     * context is available for this request.
     */
    protected function resolveCurrentOrganizationId(): ?int
    {
        $orgId = session('current_organization_id');

        return $orgId ? (int) $orgId : null;
    }

    /**
     * Resolve the current organization ID, aborting with 403 when absent.
     * Use this at the top of any action that reads or writes org-scoped data.
     */
    protected function requireCurrentOrganizationId(): int
    {
        $orgId = $this->resolveCurrentOrganizationId();

        abort_if(! $orgId, 403, 'No active organization context.');

        return $orgId;
    }

    /**
     * Resolve the current Organization model, aborting with 403 when there
     * is no organization context or the record no longer exists.
     */
    protected function requireCurrentOrganization(): Organization
    {
        return Organization::findOrFail($this->requireCurrentOrganizationId());
    }
}
