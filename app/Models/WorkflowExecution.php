<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkflowExecution extends Model
{
    use HasFactory, HasOrganizationScope;

    protected $fillable = [
        'uuid', 'workflow_id', 'organization_id', 'triggered_by', 'status',
        'input_data', 'output_data', 'step_results', 'error_message',
        'current_step', 'total_steps', 'total_cost', 'started_at', 'completed_at',
        'graph', 'is_test', 'idempotency_key', 'advancing_until',
    ];

    public const OPEN = ['pending', 'running', 'waiting_approval'];

    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'step_results' => 'array',
        'total_cost' => 'decimal:4',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $exec) {
            if (empty($exec->uuid)) {
                $exec->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return HasMany<WorkflowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class);
    }

    /** @return BelongsTo<AgentWorkflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AgentWorkflow::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
