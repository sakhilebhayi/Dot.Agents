<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AgentDeployment extends Model
{
    use HasFactory, HasOrganizationScope, SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'agent_id', 'department_id', 'team_id',
        'deployed_by', 'name', 'alias', 'custom_instructions', 'deployment_mode',
        'status', 'requires_human_approval', 'confidence_threshold',
        'model_override', 'model_config_override', 'context_config',
        'enable_memory', 'enable_long_term_memory', 'memory_retention_days',
        'risk_tolerance', 'allowed_actions', 'restricted_actions',
        'data_access_scope', 'custom_kpis', 'notification_config',
        'integration_config', 'metadata', 'deployed_at', 'last_active_at',
        'decommissioned_at', 'paused_at', 'role', 'responsibility', 'owner_id',
        'published_version_id', 'autonomy_level',
    ];

    protected $casts = [
        'custom_instructions' => 'encrypted',
        'requires_human_approval' => 'boolean',
        'enable_memory' => 'boolean',
        'enable_long_term_memory' => 'boolean',
        'confidence_threshold' => 'decimal:2',
        'risk_tolerance' => 'decimal:2',
        'model_config_override' => 'array',
        'context_config' => 'array',
        'allowed_actions' => 'array',
        'restricted_actions' => 'array',
        'data_access_scope' => 'array',
        'custom_kpis' => 'array',
        'notification_config' => 'array',
        'integration_config' => 'array',
        'metadata' => 'array',
        'deployed_at' => 'datetime',
        'last_active_at' => 'datetime',
        'decommissioned_at' => 'datetime',
        'paused_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $deployment) {
            if (empty($deployment->uuid)) {
                $deployment->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<AgentConfigVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(AgentConfigVersion::class);
    }

    /** @return BelongsTo<AgentConfigVersion, $this> */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(AgentConfigVersion::class, 'published_version_id');
    }

    /**
     * The version being worked on, if one is open.
     *
     * @return HasOne<AgentConfigVersion, $this>
     */
    public function draftVersion(): HasOne
    {
        return $this->hasOne(AgentConfigVersion::class)->where('state', AgentConfigVersion::DRAFT)->latestOfMany('number');
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function deployedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployed_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AgentSession::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AgentTask::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(AgentMemory::class);
    }

    public function decisionLogs(): HasMany
    {
        return $this->hasMany(DecisionLog::class);
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(AgentScorecard::class);
    }

    public function latestScorecard(): HasOne
    {
        return $this->hasOne(AgentScorecard::class)->latestOfMany();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AgentApproval::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function pendingApprovals(): HasMany
    {
        return $this->hasMany(AgentApproval::class)->where('status', 'pending');
    }

    // ── Skill relationships ──────────────────────────────

    public function skillAssignments(): HasMany
    {
        return $this->hasMany(AgentSkillAssignment::class);
    }

    public function skills(): HasManyThrough
    {
        return $this->hasManyThrough(
            AgentSkill::class,
            AgentSkillAssignment::class,
            'agent_deployment_id',
            'id',
            'id',
            'skill_id'
        );
    }

    public function enabledSkills(): HasMany
    {
        return $this->skillAssignments()->where('is_enabled', true)->with('skill');
    }

    public function hasSkill(string $key): bool
    {
        return $this->skillAssignments()
            ->where('is_enabled', true)
            ->whereHas('skill', fn ($q) => $q->where('key', $key))
            ->exists();
    }

    // ── Scopes ────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isAutonomous(): bool
    {
        return $this->deployment_mode === 'autonomous';
    }

    public function requiresApprovalFor(float $confidenceScore): bool
    {
        return $this->requires_human_approval || $confidenceScore < $this->confidence_threshold;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->alias ?? $this->name;
    }
}
