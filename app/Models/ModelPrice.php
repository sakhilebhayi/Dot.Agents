<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What a provider charges, as entered by a person from the provider's own price list, with the date it took effect. */
class ModelPrice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_from' => 'date'];
    }
}
