<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing an agent asked a tool to do. Written before it happens, so a crash can never leave us unsure whether we
 * meant to do it, and marked `unknown` rather than retried when the tool's answer was lost.
 *
 * @property array<string, mixed> $arguments
 * @property array<string, mixed>|null $result
 */
class AgentEffect extends Model
{
    use HasOrganizationScope;

    public const PLANNED = 'planned';

    public const APPROVED = 'approved';

    public const EXECUTING = 'executing';

    /** Handed to another agent, which will report back. */
    public const WAITING = 'waiting';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const DENIED = 'denied';

    public const UNKNOWN = 'unknown';

    /** Unknown, the model has been told, and it stays that way until a person checks. */
    public const UNRESOLVED = 'unresolved';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['arguments' => 'encrypted:array', 'result' => 'encrypted:array', 'executed_at' => 'datetime'];
    }

    /** @return BelongsTo<AgentRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }
}
