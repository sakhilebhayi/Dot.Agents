<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope for AgentSkill tenant isolation.
 *
 * Mirrors PluginOrganizationScope: a skill can be platform-wide
 * (organization_id IS NULL, the shared catalog) or belong to a specific
 * org's own custom, webhook-backed skill. Standard HasOrganizationScope
 * would hide platform-wide skills entirely, so we apply a compound WHERE:
 *
 *   WHERE (organization_id IS NULL OR organization_id = ?)
 *
 * With no business in context, only the shared rows are visible.
 */
class SkillOrganizationScope implements Scope
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
