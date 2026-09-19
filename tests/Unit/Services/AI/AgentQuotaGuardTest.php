<?php

namespace Tests\Unit\Services\AI;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\UsageRecord;
use App\Services\AI\AgentQuotaGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentQuotaGuardTest extends TestCase
{
    use RefreshDatabase;

    private function organizationWithPlan(?int $monthlyTokenQuota): Organization
    {
        $plan = SubscriptionPlan::factory()->create(['monthly_token_quota' => $monthlyTokenQuota]);
        $org = Organization::factory()->create(['plan' => $plan->slug]);

        return $org;
    }

    private function recordTokenUsage(Organization $org, int $tokens): void
    {
        UsageRecord::create([
            'organization_id' => $org->id,
            'metric_type' => 'tokens',
            'quantity' => $tokens,
            'unit_cost' => 0.00002,
            'total_cost' => $tokens * 0.00002,
            'recorded_date' => now()->toDateString(),
        ]);
    }

    public function test_allows_the_call_when_usage_is_under_the_plan_token_quota(): void
    {
        $org = $this->organizationWithPlan(1000);
        $this->recordTokenUsage($org, 500);

        app(AgentQuotaGuard::class)->assertQuotaAvailable($org->id, $org->plan);

        $this->addToAssertionCount(1); // no exception thrown
    }

    /**
     * Regression: SubscriptionPlan::$casts used to reference a nonexistent
     * max_tasks_per_month column, so $plan->max_tasks_per_month was always null,
     * $limit was always PHP_INT_MAX, and this exception could never be thrown —
     * every organization's quota was silently unlimited regardless of plan.
     */
    public function test_blocks_the_call_once_monthly_token_usage_reaches_the_plan_limit(): void
    {
        $org = $this->organizationWithPlan(1000);
        $this->recordTokenUsage($org, 1000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Monthly token quota of 1000 tokens has been reached');

        app(AgentQuotaGuard::class)->assertQuotaAvailable($org->id, $org->plan);
    }

    public function test_treats_a_negative_quota_as_unlimited_matching_this_codebases_convention(): void
    {
        $org = $this->organizationWithPlan(-1);
        $this->recordTokenUsage($org, 999_999_999);

        app(AgentQuotaGuard::class)->assertQuotaAvailable($org->id, $org->plan);

        $this->addToAssertionCount(1); // no exception thrown
    }

    public function test_treats_a_missing_plan_as_unlimited(): void
    {
        app(AgentQuotaGuard::class)->assertQuotaAvailable(1, null);

        $this->addToAssertionCount(1); // no exception thrown
    }

    public function test_only_counts_the_current_calendar_months_usage(): void
    {
        $org = $this->organizationWithPlan(1000);

        UsageRecord::create([
            'organization_id' => $org->id,
            'metric_type' => 'tokens',
            'quantity' => 900,
            'unit_cost' => 0.00002,
            'total_cost' => 0.018,
            'recorded_date' => now()->subMonthNoOverflow()->toDateString(),
        ]);
        $this->recordTokenUsage($org, 100);

        app(AgentQuotaGuard::class)->assertQuotaAvailable($org->id, $org->plan);

        $this->addToAssertionCount(1); // last month's 900 tokens don't count against this month's 1000 limit
    }
}
