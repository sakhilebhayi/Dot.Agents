<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Str;

/**
 * ManagesWorkflowCanvas
 *
 * Encapsulates all canvas mutation operations (add/connect/move/remove nodes and
 * connections) and the Alpine.js sync dispatch.
 *
 * Include in WorkflowBuilder via `use ManagesWorkflowCanvas;`.
 * The trait expects the component to have public `$nodes` and `$connections` arrays.
 */
trait ManagesWorkflowCanvas
{
    /**
     * Add a new node at the given canvas position.
     */
    public function addNode(string $agentKey, int $x = 200, int $y = 200): void
    {
        $this->nodes[] = [
            'id' => (string) Str::uuid(),
            'agent_key' => $agentKey,
            'label' => $agentKey,
            'x' => $x,
            'y' => $y,
            'config' => [],
        ];

        $this->syncCanvas();
    }

    /**
     * Connect two nodes with an optional condition.
     */
    public function connectNodes(string $fromId, string $toId, ?array $condition = null): void
    {
        if ($fromId === $toId) {
            return;
        }

        foreach ($this->connections as $existing) {
            if ($existing['from'] === $fromId && $existing['to'] === $toId) {
                return;
            }
        }

        $this->connections[] = [
            'id' => (string) Str::uuid(),
            'from' => $fromId,
            'to' => $toId,
            'condition' => $condition,
            'label' => null,
        ];

        $this->syncCanvas();
    }

    /**
     * Update a node's canvas position after drag-end.
     */
    public function moveNode(string $nodeId, int $x, int $y): void
    {
        foreach ($this->nodes as &$node) {
            if ($node['id'] === $nodeId) {
                $node['x'] = $x;
                $node['y'] = $y;
                break;
            }
        }
    }

    /**
     * Update a node's label from the Node Properties panel.
     */
    public function updateNodeLabel(string $nodeId, string $label): void
    {
        foreach ($this->nodes as &$node) {
            if ($node['id'] === $nodeId) {
                $node['label'] = $label;
                break;
            }
        }

        $this->syncCanvas();
    }

    /**
     * Remove a node and all its associated connections.
     */
    public function removeNode(string $nodeId): void
    {
        $this->nodes = array_values(
            array_filter($this->nodes, fn ($n) => $n['id'] !== $nodeId)
        );

        $this->connections = array_values(
            array_filter($this->connections, fn ($c) => $c['from'] !== $nodeId && $c['to'] !== $nodeId)
        );

        $this->syncCanvas();
    }

    /**
     * Remove a single connection edge.
     */
    public function removeConnection(string $connectionId): void
    {
        $this->connections = array_values(
            array_filter($this->connections, fn ($c) => $c['id'] !== $connectionId)
        );

        $this->syncCanvas();
    }

    /**
     * Dispatch a browser event carrying the current canvas state so Alpine
     * can update its local arrays synchronously after each server mutation.
     */
    private function syncCanvas(): void
    {
        $this->dispatch('canvas-synced', nodes: $this->nodes, connections: $this->connections);
    }

    /**
     * Populate $nodes and $connections from the persisted workflow graph.
     * Expects $this->workflow to be an AgentWorkflow instance.
     */
    protected function loadGraph(): void
    {
        $this->nodes = $this->workflow->nodes()
            ->get(['uuid', 'agent_key', 'label', 'position_x', 'position_y', 'config'])
            ->map(fn ($n) => [
                'id' => $n->uuid,
                'agent_key' => $n->agent_key,
                'label' => $n->label ?? $n->agent_key,
                'x' => $n->position_x,
                'y' => $n->position_y,
                'config' => $n->config ?? [],
            ])
            ->toArray();

        $this->connections = $this->workflow->connections()
            ->get(['uuid', 'from_node_uuid', 'to_node_uuid', 'condition', 'label'])
            ->map(fn ($c) => [
                'id' => $c->uuid,
                'from' => $c->from_node_uuid,
                'to' => $c->to_node_uuid,
                'condition' => $c->condition,
                'label' => $c->label,
            ])
            ->toArray();
    }
}
