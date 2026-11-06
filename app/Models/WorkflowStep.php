<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of one execution of a workflow, with exactly what went in and came out.
 *
 * @property ?string $input
 * @property ?string $output
 */
class WorkflowStep extends Model
{
    use HasOrganizationScope;

    public const PENDING = 'pending';

    public const RUNNING = 'running';

    public const WAITING = 'waiting_approval';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public const CANCELLED = 'cancelled';

    public const OPEN = [self::RUNNING, self::WAITING];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['input' => 'encrypted', 'output' => 'encrypted', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** @return BelongsTo<WorkflowExecution, $this> */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'workflow_execution_id');
    }

    /** @return BelongsTo<AgentRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }
}
