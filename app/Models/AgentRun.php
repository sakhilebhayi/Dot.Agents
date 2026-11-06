<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One agent doing one thing on one pinned version of its setup.
 *
 * @property array<string, int|float> $limits
 * @property ?string $input
 * @property ?string $output
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $leased_until
 * @property Carbon|null $cancel_requested_at
 */
class AgentRun extends Model
{
    use HasOrganizationScope;

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const WAITING_APPROVAL = 'waiting_approval';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const STOPPED = 'stopped';

    public const CANCELLED = 'cancelled';

    public const FINISHED = [self::COMPLETED, self::FAILED, self::STOPPED, self::CANCELLED];

    public const OPEN = [self::QUEUED, self::RUNNING, self::WAITING_APPROVAL];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'input' => 'encrypted',
            'output' => 'encrypted',
            'limits' => 'array',
            'cost' => 'decimal:6',
            'wasted_cost' => 'decimal:6',
            'is_test' => 'boolean',
            'leased_until' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $run) {
            $run->uuid = $run->uuid ?: (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<AgentDeployment, $this> */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(AgentDeployment::class, 'agent_deployment_id');
    }

    /** @return BelongsTo<AgentSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'agent_session_id');
    }

    /** @return BelongsTo<AgentConfigVersion, $this> */
    public function configVersion(): BelongsTo
    {
        return $this->belongsTo(AgentConfigVersion::class, 'config_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /** @return HasMany<AgentRunStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(AgentRunStep::class)->orderBy('sequence');
    }

    /** @return HasMany<AgentEffect, $this> */
    public function effects(): HasMany
    {
        return $this->hasMany(AgentEffect::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED, true);
    }
}
