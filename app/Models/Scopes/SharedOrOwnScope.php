<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Rows with no owner are shared with everyone. A row with an owner is visible to that business alone. */
class SharedOrOwnScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = TenantContext::id();

        if (TenantContext::isSystem() || ($organizationId === null && TenantContext::isLenient())) {
            return;
        }

        $table = $model->getTable();

        $builder->where(function (Builder $query) use ($organizationId, $table): void {
            $query->whereNull("{$table}.organization_id");

            if ($organizationId !== null) {
                $query->orWhere("{$table}.organization_id", $organizationId);
            }
        });
    }
}
