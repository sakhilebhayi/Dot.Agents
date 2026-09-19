<?php

namespace App\Events;

use App\Models\AgentDeployment;
use App\Models\AgentTask;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Fired when a run under the (provisional or any) colony runtime contract
 * exceeds its resource bound — wall-clock time or tool-call budget.
 */
class AgentRunContractBreach
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AgentDeployment $deployment,
        public readonly AgentTask $task,
        public readonly string $boundType,
        public readonly float $measuredValue,
        public readonly float $limitValue,
    ) {
        if (! in_array($boundType, ['wall_clock', 'tool_calls'], true)) {
            throw new InvalidArgumentException("boundType must be 'wall_clock' or 'tool_calls', got '{$boundType}'.");
        }
    }
}
