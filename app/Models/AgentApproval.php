<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AgentApproval extends Model
{
    use HasFactory, HasOrganizationScope;

    protected $fillable = [
        'uuid', 'task_id', 'agent_deployment_id', 'organization_id', 'requested_from',
        'approval_type', 'title', 'description', 'proposed_action', 'impact_assessment',
        'risk_level', 'confidence_score', 'status', 'reviewed_by', 'reviewer_notes',
        'reviewer_data', 'reviewed_at', 'expires_at', 'agent_run_id', 'agent_effect_id', 'action_hash', 'gate_code', 'workflow_step_id',
    ];

    protected $casts = [
        'proposed_action' => 'array',
        'impact_assessment' => 'array',
        'reviewer_data' => 'array',
        'confidence_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $approval) {
            if (empty($approval->uuid)) {
                $approval->uuid = (string) Str::uuid();
            }
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AgentTask::class);
    }

    /** @return BelongsTo<AgentRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }

    /** @return HasMany<AgentApprovalEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AgentApprovalEvent::class);
    }

    /** @return BelongsTo<AgentDeployment, $this> */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(AgentDeployment::class, 'agent_deployment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_from');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
