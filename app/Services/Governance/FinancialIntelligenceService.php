<?php

namespace App\Services\Governance;

use App\Models\AgentDeployment;
use App\Models\AgentScorecard;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Services\Governance\Financial\FinancialDimensionScorer;
use App\Services\Governance\Financial\FinancialTrendAnalyzer;
use Illuminate\Support\Facades\Cache;

/**
 * FinancialIntelligenceService — thin coordinator.
 * Dimension scoring delegated to FinancialDimensionScorer.
 * Trend analysis delegated to FinancialTrendAnalyzer.
 */
class FinancialIntelligenceService
{
    private const CACHE_TTL = 1800;

    private const LOOKBACK_MONTHS = 3;

    public function __construct(
        private readonly FinancialDimensionScorer $scorer,
        private readonly FinancialTrendAnalyzer $trendAnalyzer,
    ) {}

    public function calculate(Organization $organization): array
    {
        $cacheKey = "financial_intelligence:{$organization->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organization) {
            return $this->compute($organization);
        });
    }

    public function invalidate(Organization $organization): void
    {
        Cache::forget("financial_intelligence:{$organization->id}");
    }

    private function compute(Organization $organization): array
    {
        $since = now()->subMonths(self::LOOKBACK_MONTHS);

        $scorecardTotals = AgentScorecard::withoutGlobalScope('organization')
            ->whereHas('deployment', fn ($q) => $q->where('organization_id', $organization->id))
            ->where('period_end', '>=', $since)
            ->selectRaw('SUM(total_cost) as total_cost, SUM(estimated_savings) as total_savings, SUM(estimated_revenue_impact) as total_revenue_impact, COUNT(*) as scorecard_count')
            ->first();

        $totalCost = (float) ($scorecardTotals->total_cost ?? 0);
        $totalSavings = (float) ($scorecardTotals->total_savings ?? 0);
        $totalRevenue = (float) ($scorecardTotals->total_revenue_impact ?? 0);
        $scorecardCount = (int) ($scorecardTotals->scorecard_count ?? 0);

        $deploymentIds = AgentDeployment::withoutGlobalScope('organization')
            ->where('organization_id', $organization->id)
            ->pluck('id');

        $completedTasks = AgentTask::withoutGlobalScope('organization')
            ->whereIn('agent_deployment_id', $deploymentIds)
            ->where('status', 'completed')
            ->where('created_at', '>=', $since)
            ->count();

        $monthlyTrend = $this->trendAnalyzer->compute($deploymentIds);

        $dimensions = [];
        $score = $this->scorer->scoreROI($totalCost, $totalSavings, $dimensions);
        $score += $this->scorer->scoreCostEfficiency($totalCost, $completedTasks, $dimensions);
        $score += $this->scorer->scoreRevenueImpact($totalRevenue, $totalCost, $dimensions);
        $score += $this->scorer->scoreCostTrend($monthlyTrend, $dimensions);

        return [
            'score' => min(100, round($score, 2)),
            'dimensions' => $dimensions,
            'totals' => [
                'total_cost' => round($totalCost, 2),
                'total_savings' => round($totalSavings, 2),
                'total_revenue_impact' => round($totalRevenue, 2),
                'completed_tasks' => $completedTasks,
                'scorecard_count' => $scorecardCount,
                'roi_percentage' => $totalCost > 0
                    ? round((($totalSavings - $totalCost) / $totalCost) * 100, 2)
                    : 0,
            ],
            'monthly_trend' => $monthlyTrend,
            'recommendations' => $this->scorer->buildRecommendations($dimensions, $totalCost, $totalSavings, $scorecardCount),
        ];
    }
}
