<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a run's journal. Written once.
 *
 * @property array<string, mixed>|null $payload
 */
class AgentRunStep extends Model
{
    use HasOrganizationScope;

    public const MODEL = 'model';

    public const TOOL = 'tool';

    public const APPROVAL = 'approval';

    public const SYSTEM = 'system';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'cost' => 'decimal:6'];
    }

    /** @return BelongsTo<AgentRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }
}
