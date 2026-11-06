<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;

/** A business's own monthly limit on what its agents spend. */
class UsageBudget extends Model
{
    use HasOrganizationScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['monthly_limit' => 'decimal:2', 'stop_at_limit' => 'boolean'];
    }
}
