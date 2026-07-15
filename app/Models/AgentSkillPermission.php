<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AgentSkillPermission — platform-level catalog resource.
 *
 * Defines the permissions a skill *requires* (e.g. read_crm, write_calendar).
 * These are capability declarations on the skill catalog, not grants to any
 * specific organization. Tenant isolation here is not applicable: every org
 * that deploys a skill sees the same permission requirements. Grants are
 * managed at the AgentDeployment / organization level, not here.
 */
class AgentSkillPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'skill_id',
        'permission_key',
        'permission_label',
        'scope',
        'description',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────

    public function skill(): BelongsTo
    {
        return $this->belongsTo(AgentSkill::class, 'skill_id');
    }
}
