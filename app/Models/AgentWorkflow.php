<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AgentWorkflow extends Model
{
    use HasFactory, HasOrganizationScope, SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'created_by', 'name', 'description',
        'trigger_type', 'trigger_config', 'steps', 'agents_involved', 'status', 'is_template',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'steps' => 'array',
        'agents_involved' => 'array',
        'is_template' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $wf) {
            if (empty($wf->uuid)) {
                $wf->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class, 'workflow_id');
    }

    public function lastExecution()
    {
        return $this->hasOne(WorkflowExecution::class, 'workflow_id')->latestOfMany();
    }

    // ──────────────────────────────────────────────
    // Graph relationships
    // ──────────────────────────────────────────────

    /** @return HasMany<WorkflowNode, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class, 'workflow_id');
    }

    /** @return HasMany<WorkflowConnection, $this> */
    public function connections(): HasMany
    {
        return $this->hasMany(WorkflowConnection::class, 'workflow_id');
    }

    /** True when the workflow has been modelled as a graph (has nodes). */
    public function isGraphMode(): bool
    {
        return $this->nodes()->exists();
    }
}
