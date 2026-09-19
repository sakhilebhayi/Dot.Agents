<?php

namespace App\Services\AI;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\UsageRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AgentQuotaGuard
 *
 * Enforces the monthly token quota from the organization's subscription plan.
 *
 * Uses a cached counter for performance, invalidated at month rollover.
 * Raises a RuntimeException when the quota has been exhausted.
 *
 * Extracted from AgentOrchestrationService to keep orchestration focused.
 */
class AgentQuotaGuard
{
    /**
     * Assert that the organization has remaining token quota for this month.
     *
     * @param  string|null  $planSlug  The org's current subscription plan slug
     *
     * @throws \RuntimeException when the monthly token quota is exhausted
     */
    public function assertQuotaAvailable(int $organizationId, ?string $planSlug): void
    {
        $plan = $planSlug
            ? Cache::remember("plan:{$planSlug}", 3600, fn () => SubscriptionPlan::where('slug', $planSlug)->first())
            : null;

        $limit = $plan->monthly_token_quota ?? PHP_INT_MAX;

        // -1 is this codebase's convention for "unlimited" on other plan limits
        // (see AgentDeploymentPolicy::create() for max_agents) — honor it here too.
        if ($limit < 0) {
            $limit = PHP_INT_MAX;
        }

        if ($limit === PHP_INT_MAX) {
            return; // Unlimited — skip usage query
        }

        $cacheKey = "org_token_quota:{$organizationId}:".now()->format('Y-m');

        $used = Cache::remember($cacheKey, 300, fn () => (int) UsageRecord::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('metric_type', 'tokens')
            ->whereYear('recorded_date', now()->year)
            ->whereMonth('recorded_date', now()->month)
            ->sum('quantity')
        );

        if ($used >= $limit) {
            Log::warning('[AgentQuotaGuard] Monthly token quota exceeded', [
                'organization_id' => $organizationId,
                'used' => $used,
                'limit' => $limit,
            ]);

            throw new \RuntimeException(
                "Monthly token quota of {$limit} tokens has been reached for organization [{$organizationId}]. "
                .'Upgrade your plan or wait until next month.'
            );
        }

        // Invalidate usage cache so the next check reflects this task's token usage
        // once ResponseProcessorService::recordUsage() persists it.
        Cache::forget($cacheKey);
    }
}
