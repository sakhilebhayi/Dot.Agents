<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTeamMember extends Model
{
    use HasOrganizationScope;

    protected $guarded = ['id'];

    /** @return BelongsTo<AgentTeam, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(AgentTeam::class, 'agent_team_id');
    }

    /** @return BelongsTo<AgentDeployment, $this> */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(AgentDeployment::class, 'agent_deployment_id');
    }
}
