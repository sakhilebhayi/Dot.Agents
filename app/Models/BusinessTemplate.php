<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An agent's setup kept as a starting point for this business only.
 *
 * @property array<string, mixed> $config
 */
class BusinessTemplate extends Model
{
    use HasOrganizationScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['config' => 'encrypted:array'];
    }

    /** @param  Builder<BusinessTemplate>  $query */
    public function scopeLatestVersions(Builder $query): void
    {
        $query->whereIn('id', fn ($latest) => $latest->selectRaw('max(id)')->from('business_templates')->groupBy('organization_id', 'name'));
    }
}
