<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope for AgentPlugin tenant isolation.
 *
 * Plugins are different from other org-owned models: a plugin can be
 * platform-wide (organization_id IS NULL) or belong to a specific org.
 * Standard HasOrganizationScope would hide platform-wide plugins entirely,
 * so we apply a compound WHERE:
 *
 *   WHERE (organization_id IS NULL OR organization_id = ?)
 *
 * With no business in context, only the shared rows are visible.
 */
class PluginOrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $orgId = TenantContext::id();

        if (TenantContext::isSystem() || ($orgId === null && TenantContext::isLenient())) {
            return;
        }

        // With no business in context only what is shared with everyone can be seen.
        $builder->where(function (Builder $q) use ($orgId): void {
            $q->whereNull('organization_id');

            if ($orgId !== null) {
                $q->orWhere('organization_id', $orgId);
            }
        });
    }
}
