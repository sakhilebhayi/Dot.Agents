<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<array{name: string, passed: bool}>|null $checks
 * @property array<string, mixed>|null $measures
 */
class AgentTestResult extends Model
{
    use HasOrganizationScope;

    public const PASSED = 'passed';

    public const FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checks' => 'array', 'measures' => 'array'];
    }

    /** @return BelongsTo<AgentTestCase, $this> */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(AgentTestCase::class, 'agent_test_case_id');
    }

    /** @return BelongsTo<AgentRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }
}
