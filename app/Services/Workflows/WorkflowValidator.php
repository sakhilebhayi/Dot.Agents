<?php

namespace App\Services\Workflows;

use App\Models\AgentDeployment;
use App\Models\AgentWorkflow;
use App\Services\Tools\ToolCatalogue;

/** What stops a workflow from working, in words a person can act on, before anything runs. */
class WorkflowValidator
{
    public function __construct(private readonly ToolCatalogue $catalogue) {}

    /** @return list<string> */
    public function problems(AgentWorkflow $workflow): array
    {
        $graph = WorkflowGraph::of($workflow);

        if ($graph->nodes === []) {
            return ['Add at least one step.'];
        }

        $problems = [];

        if (count($graph->ordered()) !== count($graph->nodes)) {
            $problems[] = 'Some steps loop back on themselves, so the work would never finish.';
        }

        foreach ($graph->nodes as $uuid => $node) {
            array_push($problems, ...$this->step($workflow, $graph, $uuid, $node));
        }

        foreach ($graph->edges as $edge) {
            $condition = is_string($edge['condition']) ? json_decode($edge['condition'], true) : $edge['condition'];

            if (in_array($condition['op'] ?? null, ['contains', 'not_contains', 'equals'], true) && blank($condition['value'] ?? null)) {
                $problems[] = 'A line has a condition with nothing to compare against.';
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function step(AgentWorkflow $workflow, WorkflowGraph $graph, string $uuid, array $node): array
    {
        $label = $node['label'];
        $config = $node['config'];

        if ($node['kind'] === 'approval') {
            return $graph->into($uuid) === [] ? ["\"{$label}\" needs a step before it, so there is something to approve."] : [];
        }

        $deployment = isset($config['deployment_id'])
            ? AgentDeployment::where('organization_id', $workflow->organization_id)->find($config['deployment_id'])
            : null;

        if ($deployment === null && ! in_array($node['agent_key'], ['agent', 'tool'], true)) {
            return [];
        }

        return array_values(array_filter([
            $deployment === null ? "Choose an agent for \"{$label}\"." : null,
            $deployment !== null && $deployment->status !== 'active' ? "{$deployment->name} in \"{$label}\" is not active." : null,
            $node['kind'] === 'tool' && $this->catalogue->find($workflow->organization_id, (string) ($config['tool'] ?? '')) === null ? "Choose a tool for \"{$label}\"." : null,
        ]));
    }
}
