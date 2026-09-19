<?php

namespace App\Models\Scopes;

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
 * This scope only activates when there is an active org context in the
 * session, keeping admin/artisan/CLI contexts unrestricted.
 */
class SkillOrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $orgId = session('current_organization_id');

        if ($orgId) {
            $builder->where(function (Builder $q) use ($orgId): void {
                $q->whereNull('organization_id')
                    ->orWhere('organization_id', (int) $orgId);
            });
        }
    }
}
