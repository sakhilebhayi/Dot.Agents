<?php

namespace App\Services\Workflows;

use App\Models\AgentWorkflow;

/** The steps and lines of a workflow, as pinned when an execution starts. Later edits cannot reach work under way. */
final class WorkflowGraph
{
    /**
     * @param  array<string, array<string, mixed>>  $nodes  by uuid: label, kind, config
     * @param  list<array{from: string, to: string, condition: mixed}>  $edges
     */
    public function __construct(public readonly array $nodes, public readonly array $edges) {}

    public static function of(AgentWorkflow $workflow): self
    {
        return new self(
            $workflow->nodes()->get()->mapWithKeys(fn ($node) => [$node->uuid => [
                'label' => $node->label ?? $node->agent_key,
                'agent_key' => $node->agent_key,
                'kind' => $node->config['kind'] ?? 'agent',
                'config' => $node->config ?? [],
            ]])->all(),
            $workflow->connections()->get()->map(fn ($edge) => ['from' => $edge->from_node_uuid, 'to' => $edge->to_node_uuid, 'condition' => $edge->condition])->all(),
        );
    }

    /** @param  array{nodes: array<string, array<string, mixed>>, edges: list<array{from: string, to: string, condition: mixed}>}  $stored */
    public static function restore(array $stored): self
    {
        return new self($stored['nodes'], $stored['edges']);
    }

    /** @return array{nodes: array<string, array<string, mixed>>, edges: list<array{from: string, to: string, condition: mixed}>} */
    public function store(): array
    {
        return ['nodes' => $this->nodes, 'edges' => $this->edges];
    }

    /** @return list<array{from: string, to: string, condition: mixed}> */
    public function into(string $uuid): array
    {
        return array_values(array_filter($this->edges, fn ($edge) => $edge['to'] === $uuid));
    }

    /** @return list<array{from: string, to: string, condition: mixed}> */
    public function outOf(string $uuid): array
    {
        return array_values(array_filter($this->edges, fn ($edge) => $edge['from'] === $uuid));
    }

    /** @return list<string> Node ids with parents before children, or a shorter list when there is a loop. */
    public function ordered(): array
    {
        $waiting = array_map(fn ($uuid) => count($this->into($uuid)), array_combine(array_keys($this->nodes), array_keys($this->nodes)));
        $ready = array_keys(array_filter($waiting, fn ($count) => $count === 0));
        $ordered = [];

        while ($ready !== []) {
            $uuid = array_shift($ready);
            $ordered[] = $uuid;

            foreach ($this->outOf($uuid) as $edge) {
                if (isset($waiting[$edge['to']]) && --$waiting[$edge['to']] === 0) {
                    $ready[] = $edge['to'];
                }
            }
        }

        return $ordered;
    }
}
