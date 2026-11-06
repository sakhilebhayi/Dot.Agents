<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps a model inside the business in TenantContext.
 *
 * With no business in context the query throws, so a job or command that forgot to say whose data it wants fails
 * loudly instead of reading every tenant. Work across businesses declares it with TenantContext::system(), and a
 * single deliberate lookup uses forOrganization() or withoutGlobalScope('organization').
 */
trait HasOrganizationScope
{
    /**
     * Boot the trait — register the global scope once per model class.
     */
    public static function bootHasOrganizationScope(): void
    {
        static::addGlobalScope('organization', function (Builder $query) {
            if (TenantContext::isSystem()) {
                return;
            }

            $organizationId = TenantContext::id();

            if ($organizationId !== null) {
                $query->where((new self)->getTable().'.organization_id', $organizationId);
            } elseif (! TenantContext::isLenient()) {
                throw TenantContext::missing(self::class);
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
