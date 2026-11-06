<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;

/** A business's own rule: this kind of action always goes to a person, and to whom. A rule can only add a check. */
class ApprovalRule extends Model
{
    use HasOrganizationScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
