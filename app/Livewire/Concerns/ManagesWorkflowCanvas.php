<?php

namespace App\Livewire\Concerns;

/** Loads a saved workflow into the shape the diagram works with. */
trait ManagesWorkflowCanvas
{
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
