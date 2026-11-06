<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;

/** One charge. Append only: a price change never rewrites history. A null cost means no price was on file. */
class AgentUsageEvent extends Model
{
    use HasOrganizationScope;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cost' => 'decimal:6', 'wasted' => 'boolean'];
    }
}
