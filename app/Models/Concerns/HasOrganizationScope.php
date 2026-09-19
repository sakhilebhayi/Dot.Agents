<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * HasOrganizationScope — auto-scopes queries to the current organization.
 *
 * Apply this trait to any model that has an `organization_id` column and
 * should only ever be queried within the context of a single organization.
 *
 * The global scope is only active when a `current_organization_id` value
 * is present in the session, so background jobs (no session) are unaffected.
 *
 * Usage:
 *   use App\Models\Concerns\HasOrganizationScope;
 *   class AgentDeployment extends Model {
 *       use HasOrganizationScope;
 *   }
 *
 * To bypass the scope (e.g. in admin controllers or cross-org queries):
 *   AgentDeployment::withoutGlobalScope('organization')->get();
 */
trait HasOrganizationScope
{
    /**
     * Boot the trait — register the global scope once per model class.
     */
    public static function bootHasOrganizationScope(): void
    {
        static::addGlobalScope('organization', function (Builder $query) {
            $organizationId = session('current_organization_id');

            if ($organizationId) {
                $model = new self;
                $table = $model->getTable();

                $query->where("{$table}.organization_id", (int) $organizationId);
            }
        });
    }

    /**
     * Convenience scope: explicitly scope a query to a specific organization.
     * Bypasses the global scope value so you can target any org.
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->withoutGlobalScope('organization')
            ->where($this->getTable().'.organization_id', $organizationId);
    }
}
