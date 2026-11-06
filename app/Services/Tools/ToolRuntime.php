<?php

namespace App\Services\Tools;

use App\Models\AgentRun;

/** Placeholder until the tool layer lands in A4: no agent has tools yet. */
class ToolRuntime
{
    public const WORKED = 'worked';

    public const WAITING = 'waiting';

    public const NOTHING = 'nothing';

    /** @return list<array{name: string, description: string, schema: array<string, mixed>}> */
    public function definitions(AgentRun $run): array
    {
        return [];
    }

    /** @param  list<array{id: string, name: string, arguments: array<string, mixed>}>  $calls */
    public function plan(AgentRun $run, array $calls): void {}

    public function work(AgentRun $run): string
    {
        return self::NOTHING;
    }
}
