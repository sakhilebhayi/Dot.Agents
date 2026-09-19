<?php

namespace App\Services\AI;

use App\Models\AgentDeployment;
use Illuminate\Support\Facades\Log;

/**
 * Enforces execution boundaries for an AI agent during task processing.
 *
 * Sandboxing boundaries enforced:
 *  1. Tenant isolation — prevents cross-org data access
 *  2. Permission scope  — validates requested action against allowed_actions
 *  3. Memory isolation  — agent can only access its own deployment memory
 *  4. Tool restrictions — validates tool use against deployment config + DB rules
 *  5. Token budget      — prevents runaway token spend per task
 *  6. Tool-call budget  — prevents runaway tool-call loops per task
 */
class AgentSandboxService
{
    private const MAX_TOKENS_PER_TASK = 32000;

    /**
     * Maximum number of nested agent delegations allowed.
     *
     * Prevents runaway agent delegation chains (A → B → C → D → ...).
     * Exceeding this limit raises a RuntimeException and is audit-logged.
     * CVE mitigation: prevents recursive delegation amplification attacks.
     */
    private const MAX_DELEGATION_DEPTH = 3;

    /**
     * Maximum number of tool calls allowed within a single task execution,
     * for any deployment regardless of charter/provisional status.
     *
     * Provisional (uncharted) agents already have a stricter, event-driven
     * limit enforced in AgentOrchestrationService::executeTask() via the
     * colony runtime contract (config('services.dot_brain.provisional_max_tool_calls')),
     * escalating straight to quarantine. This is the broader safety net that
     * also covers chartered agents, which otherwise have no tool-call limit
     * at all — prevents a runaway tool-call loop (e.g. a cheap tool invoked
     * indefinitely) regardless of trust tier.
     */
    private const MAX_TOOL_CALLS_PER_TASK = 15;

    public function __construct(
        private readonly ToolPermissionService $toolPermissions,
    ) {}

    /**
     * Validate that an agent action is permitted within its sandbox.
     *
     * @throws \RuntimeException if the action violates sandbox rules
     */
    public function assertPermitted(
        AgentDeployment $deployment,
        string $action,
        array $context = [],
    ): void {
        $this->enforceTenantIsolation($deployment, $context);
        $this->enforceActionPermission($deployment, $action);
        $this->enforceToolRestrictions($deployment, $action, $context);
    }

    /**
     * Check whether a token budget is within the per-task limit.
     *
     * @throws \RuntimeException if the token budget is exceeded
     */
    public function enforceTokenBudget(AgentDeployment $deployment, int $tokensUsed): void
    {
        $limit = $deployment->agent?->model_config['max_tokens_per_task'] ?? self::MAX_TOKENS_PER_TASK;

        if ($tokensUsed > $limit) {
            Log::warning('AgentSandboxService: token budget exceeded', [
                'deployment_id' => $deployment->id,
                'tokens_used' => $tokensUsed,
                'limit' => $limit,
            ]);

            throw new \RuntimeException(
                "Token budget exceeded for deployment [{$deployment->id}]. Used: {$tokensUsed}, Limit: {$limit}."
            );
        }
    }

    /**
     * Check whether a tool-call count is within the per-task limit.
     *
     * @throws \RuntimeException if the tool-call limit is exceeded
     */
    public function enforceToolCallLimit(AgentDeployment $deployment, int $toolCallCount): void
    {
        $limit = $deployment->agent?->model_config['max_tool_calls_per_task'] ?? self::MAX_TOOL_CALLS_PER_TASK;

        if ($toolCallCount > $limit) {
            Log::warning('AgentSandboxService: tool-call limit exceeded', [
                'deployment_id' => $deployment->id,
                'tool_calls_used' => $toolCallCount,
                'limit' => $limit,
            ]);

            throw new \RuntimeException(
                "Tool-call limit exceeded for deployment [{$deployment->id}]. Used: {$toolCallCount}, Limit: {$limit}."
            );
        }
    }

    /**
     * Validate a memory key belongs to this deployment (tenant/memory isolation).
     *
     * @throws \RuntimeException if the key belongs to another deployment
     */
    public function assertMemoryOwnership(AgentDeployment $deployment, string $memoryKey): void
    {
        // Memory keys are namespaced: "deployment:{id}:{key}"
        if (! str_starts_with($memoryKey, "deployment:{$deployment->id}:")) {
            Log::error('AgentSandboxService: memory isolation violation', [
                'deployment_id' => $deployment->id,
                'memory_key' => $memoryKey,
            ]);

            throw new \RuntimeException(
                "Memory isolation violation: deployment [{$deployment->id}] cannot access key [{$memoryKey}]."
            );
        }
    }

    /**
     * Return a sandboxed memory key for this deployment.
     */
    public function namespaceMemoryKey(AgentDeployment $deployment, string $key): string
    {
        return "deployment:{$deployment->id}:{$key}";
    }

    /**
     * Enforce the maximum delegation depth for nested agent-to-agent calls.
     *
     * Call this before an agent delegates a subtask to another agent.
     * The $currentDepth is the number of delegation hops already taken.
     *
     * @param  AgentDeployment  $delegation  The agent being delegated TO
     * @param  int  $currentDepth  Current delegation chain depth (0 = root)
     *
     * @throws \RuntimeException if the depth limit would be exceeded
     */
    public function enforceDelegationDepth(AgentDeployment $delegation, int $currentDepth): void
    {
        if ($currentDepth >= self::MAX_DELEGATION_DEPTH) {
            Log::error('AgentSandboxService: delegation depth limit exceeded', [
                'delegation_deployment_id' => $delegation->id,
                'current_depth' => $currentDepth,
                'max_depth' => self::MAX_DELEGATION_DEPTH,
            ]);

            throw new \RuntimeException(
                "Delegation depth limit exceeded ({$currentDepth}/".self::MAX_DELEGATION_DEPTH.'). '
                ."Deployment [{$delegation->id}] cannot accept further nested delegations."
            );
        }

        Log::debug('AgentSandboxService: delegation depth check passed', [
            'delegation_deployment_id' => $delegation->id,
            'depth' => $currentDepth + 1,
            'max_depth' => self::MAX_DELEGATION_DEPTH,
        ]);
    }

    // ─── Private enforcement methods ────────────────────────────────────────────

    private function enforceTenantIsolation(AgentDeployment $deployment, array $context): void
    {
        $contextOrgId = $context['organization_id'] ?? null;

        if ($contextOrgId !== null && (int) $contextOrgId !== $deployment->organization_id) {
            Log::critical('AgentSandboxService: tenant isolation violation', [
                'deployment_id' => $deployment->id,
                'deployment_org' => $deployment->organization_id,
                'context_org' => $contextOrgId,
            ]);

            throw new \RuntimeException(
                "Tenant isolation violation: deployment [{$deployment->id}] tried to access org [{$contextOrgId}]."
            );
        }
    }

    private function enforceActionPermission(AgentDeployment $deployment, string $action): void
    {
        $allowedActions = $deployment->allowed_actions ?? [];
        $restrictedActions = $deployment->restricted_actions ?? [];

        if (in_array($action, $restrictedActions, true)) {
            Log::warning('AgentSandboxService: restricted action blocked', [
                'deployment_id' => $deployment->id,
                'action' => $action,
            ]);

            throw new \RuntimeException(
                "Action [{$action}] is explicitly restricted for deployment [{$deployment->id}]."
            );
        }

        if (! empty($allowedActions) && ! in_array($action, $allowedActions, true)) {
            Log::warning('AgentSandboxService: action not in allowlist', [
                'deployment_id' => $deployment->id,
                'action' => $action,
            ]);

            throw new \RuntimeException(
                "Action [{$action}] is not in the allowlist for deployment [{$deployment->id}]."
            );
        }
    }

    private function enforceToolRestrictions(
        AgentDeployment $deployment,
        string $action,
        array $context,
    ): void {
        $tool = $context['tool'] ?? null;

        if (! $tool) {
            return;
        }

        // 1. Agent-level tool registration check (fast — in-memory)
        $agentTools = $deployment->agent->tools ?? [];

        if (! empty($agentTools) && ! in_array($tool, $agentTools, true)) {
            Log::warning('AgentSandboxService: tool not registered for agent', [
                'deployment_id' => $deployment->id,
                'tool' => $tool,
            ]);

            throw new \RuntimeException(
                "Tool [{$tool}] is not registered for agent in deployment [{$deployment->id}]."
            );
        }

        // 2. Per-tool permission check (database-backed, cached 5 min)
        $userRole = $context['user_role'] ?? null;
        $this->toolPermissions->assertToolPermitted($deployment, $tool, $userRole);
    }
}
