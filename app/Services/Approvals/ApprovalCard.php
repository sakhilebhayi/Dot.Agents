<?php

namespace App\Services\Approvals;

use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Services\Tools\GateDecision;
use App\Services\Tools\ToolDefinition;
use App\Support\RunTaint;
use Illuminate\Support\Str;

/**
 * What a reviewer reads. Everything stated as fact comes from the system: the tool's own definition and the exact values
 * that will be used. What the model said about why is kept apart and labelled as the agent's words, because the model
 * may have been misled, and a card must never be something a model can write its own way past.
 */
class ApprovalCard
{
    /** @return array<string, mixed> */
    public function build(AgentRun $run, AgentEffect $effect, ToolDefinition $tool, GateDecision $decision): array
    {
        return [
            'agent' => $run->deployment->name,
            'what' => $tool->label,
            'tool' => $tool->key,
            'version' => $tool->version,
            'values' => $effect->arguments,
            'person' => $this->person($run, $effect->arguments['user_id'] ?? null),
            'consequence' => $this->consequence($tool),
            'permissions' => $tool->accesses,
            'if_rejected' => 'The agent is told no, and it carries on without this step or stops.',
            'held_because' => $decision->reason,
            'outside_content' => RunTaint::has($run, RunTaint::UNTRUSTED),
            'agent_says' => $this->agentSays($run),
        ];
    }

    /** A reviewer should see who, not a number, and the name comes from the business's own records. */
    private function person(AgentRun $run, mixed $id): ?string
    {
        return is_int($id) ? $run->deployment->organization->users()->whereKey($id)->value('name') : null;
    }

    public function risk(ToolDefinition $tool): string
    {
        return match (true) {
            $tool->ceiling !== null => 'critical',
            $tool->writesState && $tool->externalReach => 'high',
            $tool->writesState => 'medium',
            default => 'low',
        };
    }

    private function consequence(ToolDefinition $tool): string
    {
        return match (true) {
            $tool->ceiling !== null => "This is {$tool->ceiling}.",
            $tool->writesState && $tool->externalReach => 'This changes something outside this platform, and may not be reversible.',
            $tool->writesState => 'This changes something on this platform.',
            $tool->externalReach => 'This sends a request outside this platform. The address and values leave the platform.',
            default => 'This only reads information.',
        };
    }

    private function agentSays(AgentRun $run): ?string
    {
        $turn = AgentRunStep::where('agent_run_id', $run->id)->where('kind', AgentRunStep::MODEL)->orderByDesc('sequence')->first();
        $text = trim((string) ($turn?->payload['message']['text'] ?? ''));

        return $text === '' ? null : Str::limit($text, 600);
    }
}
