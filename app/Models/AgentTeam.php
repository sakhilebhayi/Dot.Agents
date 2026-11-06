<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Agents who work together, under one agent that hands out the work. */
class AgentTeam extends Model
{
    use HasOrganizationScope;

    protected $guarded = ['id'];

    /** @return BelongsTo<AgentDeployment, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(AgentDeployment::class, 'supervisor_deployment_id');
    }

    /** @return HasMany<AgentTeamMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(AgentTeamMember::class);
    }
}
