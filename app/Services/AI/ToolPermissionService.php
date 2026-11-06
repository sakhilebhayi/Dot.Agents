<?php

namespace App\Services\AI;

use App\Models\AgentDeployment;
use App\Models\AgentToolPermission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tool Permission Service
 *
 * Enforces fine-grained per-tool access control for AI agents.
 * Supports an org-wide default ruleset overlaid with per-deployment
 * overrides, following a deny-wins merge strategy:
 *
 *   1. Deployment-level DENY  → denied (highest priority)
 *   2. Org-level DENY         → denied
 *   3. Deployment-level ALLOW → allowed
 *   4. Org-level ALLOW        → allowed
 *   5. No rule found          → DENY: a tool has to be given, never assumed
 *
 * Role-scoped rules are evaluated if the requesting user has a matching role.
 */
class ToolPermissionService
{
    private const CACHE_TTL = 300; // 5-minute permission cache

    /**
     * Check if a deployment is permitted to use a specific tool.
     *
     * @throws \RuntimeException when tool use is denied
     */
    public function assertToolPermitted(
        AgentDeployment $deployment,
        string $toolName,
        ?string $userRole = null
    ): void {
        if (! $this->isToolPermitted($deployment, $toolName, $userRole)) {
            Log::warning('[ToolPermission] Tool access denied', [
                'deployment_id' => $deployment->id,
                'tool' => $toolName,
                'user_role' => $userRole,
                'organization_id' => $deployment->organization_id,
            ]);

            throw new \RuntimeException(
                "Tool [{$toolName}] is not permitted for deployment [{$deployment->id}]."
            );
        }
    }

    /**
     * Check (without throwing) if a tool is permitted.
     */
    public function isToolPermitted(
        AgentDeployment $deployment,
        string $toolName,
        ?string $userRole = null
    ): bool {
        $generation = Cache::get("tool_perm_generation_{$deployment->id}", '0');
        $cacheKey = "tool_perm_{$generation}_{$deployment->id}_{$toolName}_".($userRole ?? 'any');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($deployment, $toolName, $userRole) {
            return $this->evaluatePermission($deployment, $toolName, $userRole);
        });
    }

    /** A business can take a tool away from an agent that was given it. Asked of the database, every time. */
    public function isDenied(AgentDeployment $deployment, string $toolName): bool
    {
        return AgentToolPermission::query()
            ->where('organization_id', $deployment->organization_id)
            ->where(fn ($query) => $query->whereNull('agent_deployment_id')->orWhere('agent_deployment_id', $deployment->id))
            ->where('tool_name', $toolName)->whereNull('role_scope')->where('permission', 'deny')->where('is_active', true)
            ->exists();
    }

    /**
     * Get all permitted tools for a deployment (for UI display).
     */
    public function getPermittedTools(AgentDeployment $deployment, ?string $userRole = null): array
    {
        $orgRules = AgentToolPermission::where('organization_id', $deployment->organization_id)
            ->whereNull('agent_deployment_id')
            ->where('is_active', true)
            ->get();

        $deploymentRules = AgentToolPermission::where('agent_deployment_id', $deployment->id)
            ->where('is_active', true)
            ->get();

        $allowed = [];
        $denied = [];

        foreach ($orgRules->merge($deploymentRules) as $rule) {
            if ($rule->role_scope && $userRole && $rule->role_scope !== $userRole) {
                continue;
            }

            if ($rule->permission === 'deny') {
                $denied[] = $rule->tool_name;
            } else {
                $allowed[] = $rule->tool_name;
            }
        }

        return array_values(array_diff($allowed, $denied));
    }

    /**
     * Invalidate cached permissions for a deployment (call after rule changes).
     */
    public function invalidateCache(int $deploymentId): void
    {
        // Rules are few and the cache short, so a generation number retires every key at once.
        Cache::forever("tool_perm_generation_{$deploymentId}", (string) Str::ulid());
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function evaluatePermission(
        AgentDeployment $deployment,
        string $toolName,
        ?string $userRole
    ): bool {
        // Load deployment-specific rules (highest priority)
        $deploymentRules = AgentToolPermission::where('agent_deployment_id', $deployment->id)
            ->where('tool_name', $toolName)
            ->where('is_active', true)
            ->get();

        // Deployment DENY wins immediately
        foreach ($deploymentRules as $rule) {
            if ($this->ruleApplies($rule, $userRole) && $rule->permission === 'deny') {
                return false;
            }
        }

        // Load org-wide rules
        $orgRules = AgentToolPermission::where('organization_id', $deployment->organization_id)
            ->whereNull('agent_deployment_id')
            ->where('tool_name', $toolName)
            ->where('is_active', true)
            ->get();

        // Org DENY wins
        foreach ($orgRules as $rule) {
            if ($this->ruleApplies($rule, $userRole) && $rule->permission === 'deny') {
                return false;
            }
        }

        // Explicit ALLOW at deployment level
        foreach ($deploymentRules as $rule) {
            if ($this->ruleApplies($rule, $userRole) && $rule->permission === 'allow') {
                return true;
            }
        }

        // Explicit ALLOW at org level
        foreach ($orgRules as $rule) {
            if ($this->ruleApplies($rule, $userRole) && $rule->permission === 'allow') {
                return true;
            }
        }

        return false;
    }

    private function ruleApplies(AgentToolPermission $rule, ?string $userRole): bool
    {
        // Rule without role scope applies to everyone
        if ($rule->role_scope === null) {
            return true;
        }

        return $userRole !== null && $rule->role_scope === $userRole;
    }
}
