<?php

namespace App\Services\Runs;

use App\Jobs\RunAgentStep;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Support\MarkedData;

/** When a teammate finishes, its supervisor hears the result, and takes on whatever the teammate was exposed to. */
class DelegationReturn
{
    public function __construct(private readonly ToolJournal $journal) {}

    public function back(AgentRun $child): void
    {
        $effect = AgentEffect::where('child_run_id', $child->id)->where('status', AgentEffect::WAITING)->first();
        $parent = AgentRun::find($child->parent_run_id);

        if ($effect === null || $parent === null) {
            return;
        }

        $worked = $child->status === AgentRun::COMPLETED;
        $content = $worked ? MarkedData::wrap('teammate', (string) $child->output) : "Your teammate could not finish: {$child->error_message}";

        $effect->update(['status' => $worked ? AgentEffect::DONE : AgentEffect::FAILED, 'result' => ['content' => $content]]);
        $parent->update(['taint' => $parent->taint | $child->taint]);
        $this->journal->result($parent, $effect, 'A teammate reported back', $content, $worked ? [] : ['error' => true]);

        if ($parent->status === AgentRun::WAITING_AGENT) {
            $parent->update(['status' => AgentRun::RUNNING]);
            RunAgentStep::dispatch($parent->id, $parent->organization_id);
        }
    }
}
