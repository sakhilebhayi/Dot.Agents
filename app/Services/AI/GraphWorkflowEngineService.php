<?php

namespace App\Services\AI;

use App\Models\AgentWorkflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowNode;
use App\Services\AI\Workflow\WorkflowGraphResolver;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Graph Workflow Engine Service
 *
 * Executes agent workflows modelled as a directed acyclic graph (DAG).
 * Supports:
 *  – Conditional branching via edge conditions
 *  – Multi-agent chaining (output of node A flows into node B)
 *  – Cycle detection (guards against infinite loops)
 *  – Parallel fan-out when multiple edges leave a node
 *  – Max-node circuit breaker (prevents workflow bombs)
 *  – Per-org execution rate limiting (prevents DoS via workflow flooding)
 *
 * Node output is merged into a shared execution context keyed by node UUID,
 * so downstream nodes can reference results from any upstream node.
 */
class GraphWorkflowEngineService
{
    /** Hard cap on nodes executed per workflow run — prevents workflow bombs. */
    private const MAX_NODES_PER_EXECUTION = 100;

    public function __construct(
        private readonly AgentOrchestrationService $orchestrator,
        private readonly AuditService $auditService,
        private readonly WorkflowRiskScoringService $riskScorer,
        private readonly WorkflowGraphResolver $graphResolver,
    ) {}

    // ──────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────

    /**
     * Execute a workflow graph and return the final merged context.
     *
     * @throws \RuntimeException if the org has exceeded the per-minute execution rate limit
     */
    public function execute(AgentWorkflow $workflow, array $initialInput = [], ?int $triggeredBy = null): WorkflowExecution
    {
        // ── Per-org execution rate limit ─────────────────────────────────────
        $this->graphResolver->enforceExecutionRateLimit($workflow->organization_id);

        // ── Pre-execution risk assessment ────────────────────────────────────
        $risk = $this->riskScorer->assess($workflow);
        if ($risk['is_blocked']) {
            throw new \RuntimeException(
                "Workflow [{$workflow->id}] blocked by risk scoring engine. Risk score: {$risk['score']}/100 (level: {$risk['level']}). Review workflow configuration before execution."
            );
        }

        $execution = WorkflowExecution::create([
            'uuid' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'organization_id' => $workflow->organization_id,
            'triggered_by' => $triggeredBy,
            'status' => 'running',
            'current_step' => 0,
            'input_data' => $initialInput,
            'step_results' => [],
            'started_at' => now(),
        ]);

        $this->auditService->logUserAction(
            event: 'workflow.graph.started',
            description: "Graph workflow '{$workflow->name}' execution started",
            subject: $workflow,
            metadata: ['execution_id' => $execution->id]
        );

        try {
            $context = $this->runGraph($workflow, $execution, $initialInput);

            $execution->update([
                'status' => 'completed',
                'output_data' => $context,
                'completed_at' => now(),
            ]);

            $this->auditService->logUserAction(
                event: 'workflow.graph.completed',
                description: "Graph workflow '{$workflow->name}' completed successfully",
                subject: $workflow,
                metadata: ['execution_id' => $execution->id, 'nodes_executed' => count($context)]
            );
        } catch (Throwable $e) {
            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            Log::error('[GraphWorkflowEngine] Execution failed', [
                'workflow_id' => $workflow->id,
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $execution->refresh();
    }

    // ──────────────────────────────────────────────
    // Graph traversal
    // ──────────────────────────────────────────────

    /**
     * BFS traversal of the graph, executing each reachable node in topological order.
     * Returns the final merged context (keyed by node UUID).
     */
    private function runGraph(AgentWorkflow $workflow, WorkflowExecution $execution, array $initialInput): array
    {
        // Eager-load graph structure
        $workflow->load(['nodes', 'connections']);

        $nodes = $workflow->nodes->keyBy('uuid');         // uuid → WorkflowNode
        $connections = $workflow->connections;

        $context = ['__input' => $initialInput];
        $visited = [];
        $queue = $this->graphResolver->resolveStartNodes($nodes, $connections);
        $stepResults = [];
        $stepIndex = 0;
        $nodesExecuted = 0;

        while ($queue->isNotEmpty()) {
            /** @var WorkflowNode $node */
            $node = $queue->shift();

            if (isset($visited[$node->uuid])) {
                continue;
            }

            // ── Circuit Breaker: max-node cap ─────────────────────────────────
            if (++$nodesExecuted > self::MAX_NODES_PER_EXECUTION) {
                $msg = "Workflow [{$workflow->id}] exceeded MAX_NODES_PER_EXECUTION (".self::MAX_NODES_PER_EXECUTION.'). Execution halted to prevent workflow bomb.';
                Log::error('[GraphWorkflowEngine] Circuit breaker tripped — max node cap', [
                    'workflow_id' => $workflow->id,
                    'execution_id' => $execution->id,
                    'nodes_executed' => $nodesExecuted,
                ]);
                throw new \RuntimeException($msg);
            }

            $visited[$node->uuid] = true;

            Log::debug('[GraphWorkflowEngine] Executing node', [
                'node_uuid' => $node->uuid,
                'agent_key' => $node->agent_key,
            ]);

            $result = $this->executeNode($node, $context, $execution);

            // Store result in context keyed by node UUID
            $context[$node->uuid] = $result;

            $stepResults[$stepIndex] = [
                'node_uuid' => $node->uuid,
                'agent_key' => $node->agent_key,
                'status' => $result['status'] ?? 'unknown',
                'output' => $result['output'] ?? null,
            ];

            $execution->update([
                'current_step' => ++$stepIndex,
                'step_results' => $stepResults,
            ]);

            // Resolve which nodes come next, respecting edge conditions
            $nextNodes = $this->graphResolver->resolveNextNodes($connections, $nodes, $node, $result);
            foreach ($nextNodes as $next) {
                $queue->push($next);
            }
        }

        return $context;
    }

    // ──────────────────────────────────────────────
    // Node execution
    // ──────────────────────────────────────────────

    private function executeNode(WorkflowNode $node, array $context, WorkflowExecution $execution): array
    {
        try {
            $result = $this->orchestrator->executeGraphNode(
                agentKey: $node->agent_key,
                context: $context,
                nodeConfig: $node->config ?? [],
                metadata: [
                    'workflow_id' => $execution->workflow_id,
                    'execution_id' => $execution->id,
                    'node_uuid' => $node->uuid,
                ]
            );

            return array_merge(['status' => 'completed'], $result);
        } catch (Throwable $e) {
            Log::warning('[GraphWorkflowEngine] Node execution failed', [
                'node_uuid' => $node->uuid,
                'agent_key' => $node->agent_key,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

}
