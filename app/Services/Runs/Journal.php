<?php

namespace App\Services\Runs;

use App\Models\AgentRun;
use App\Models\AgentRunStep;

/** Appends to a run's journal. Steps are written once and never changed. */
class Journal
{
    /** @param  array<string, mixed>  $attributes */
    public function write(AgentRun $run, string $kind, string $title, array $attributes = []): AgentRunStep
    {
        return AgentRunStep::create([
            'organization_id' => $run->organization_id,
            'agent_run_id' => $run->id,
            'sequence' => (int) AgentRunStep::where('agent_run_id', $run->id)->max('sequence') + 1,
            'kind' => $kind,
            'title' => mb_substr($title, 0, 200),
            ...$attributes,
        ]);
    }
}
