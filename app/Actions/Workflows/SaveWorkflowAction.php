<?php

namespace App\Actions\Workflows;

use App\DTOs\Workflows\SaveWorkflowData;
use App\Events\WorkflowSaved;
use App\Models\AgentWorkflow;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SaveWorkflowAction
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Persist workflow nodes and connections from the canvas state.
     *
     * @param  array  $nodes  Raw node array from Alpine canvas
     * @param  array  $connections  Raw connection array from Alpine canvas
     */
    public function execute(AgentWorkflow $workflow, SaveWorkflowData $data): AgentWorkflow
    {
        Gate::authorize('update', $workflow);

        $nodes = $data->nodes;
        $connections = $data->connections;

        // Validate inputs
        foreach ($nodes as $node) {
            throw_unless(
                isset($node['id'], $node['agent_key']),
                \InvalidArgumentException::class,
                'Each node must have an id and agent_key.'
            );
        }

        foreach ($connections as $conn) {
            throw_unless(
                isset($conn['from'], $conn['to']),
                \InvalidArgumentException::class,
                'Each connection must have from and to node references.'
            );
        }

        // Replace all nodes
        $workflow->nodes()->delete();

        foreach ($nodes as $node) {
            $workflow->nodes()->create([
                'uuid' => $node['id'] ?? (string) Str::uuid(),
                'agent_key' => $node['agent_key'],
                'label' => $node['label'] ?? $node['agent_key'],
                'position_x' => $node['x'] ?? 0,
                'position_y' => $node['y'] ?? 0,
                'config' => $node['config'] ?? [],
                'organization_id' => $workflow->organization_id,
            ]);
        }

        // Replace all connections
        $workflow->connections()->delete();

        foreach ($connections as $conn) {
            $workflow->connections()->create([
                'uuid' => $conn['id'] ?? (string) Str::uuid(),
                'from_node_uuid' => $conn['from'],
                'to_node_uuid' => $conn['to'],
                'condition' => $conn['condition'] ?? null,
                'label' => $conn['label'] ?? null,
                'organization_id' => $workflow->organization_id,
            ]);
        }

        $this->auditService->logUserAction(
            event: 'workflow.saved',
            description: "Workflow '{$workflow->name}' saved with ".count($nodes).' nodes',
            subject: $workflow,
        );

        $fresh = $workflow->fresh();

        event(new WorkflowSaved($fresh, count($nodes)));

        return $fresh;
    }
}
