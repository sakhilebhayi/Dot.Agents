<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A question with a known good answer, kept so every new version of an agent can be held to it.
 *
 * @property array<string, mixed> $checks
 */
class AgentTestCase extends Model
{
    use HasOrganizationScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checks' => 'array'];
    }

    /** @return HasMany<AgentTestResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(AgentTestResult::class);
    }
}
