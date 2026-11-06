<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An API a business connected, as a tool. The address and the credentials come from a person, never from a model.
 *
 * @property array<string, string>|null $headers
 * @property array<string, mixed> $input_schema
 */
class CustomTool extends Model
{
    use HasOrganizationScope;

    public const PUBLISHED = 'published';

    public const RETIRED = 'retired';

    protected $guarded = ['id'];

    protected $hidden = ['headers'];

    protected function casts(): array
    {
        return ['headers' => 'encrypted:array', 'input_schema' => 'array', 'reads_sensitive' => 'boolean'];
    }

    /** @param  Builder<CustomTool>  $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', self::PUBLISHED)->whereIn('id', fn ($latest) => $latest->selectRaw('max(id)')->from('custom_tools')->groupBy('organization_id', 'key'));
    }
}
