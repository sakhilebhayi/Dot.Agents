<?php

namespace App\Services\Governance;

use App\Models\AgentDeployment;
use App\Models\AgentScorecard;
use App\Models\AgentTask;
use App\Models\DecisionLog;
use Carbon\Carbon;

class ScorecardService
{
    /**
     * Calculate and store a scorecard for an agent deployment.
     */
    public function calculatePeriodScorecard(
        AgentDeployment $deployment,
        string $period = 'monthly',
        ?Carbon $date = null
    ): AgentScorecard {
        $date ??= now();

        [$start, $end] = $this->getPeriodDates($period, $date);

        $tasks = AgentTask::where('agent_deployment_id', $deployment->id)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $decisions = DecisionLog::where('agent_deployment_id', $deployment->id)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'completed')->count();
        $failedTasks = $tasks->where('status', 'failed')->count();

        // Accuracy: percentage of tasks without delusion flags
        $highRiskDecisions = $decisions->filter(fn ($d) => $d->delusion_risk_score >= 60)->count();
        $totalDecisions = $decisions->count();
        $accuracyScore = $totalDecisions > 0
            ? max(0, 100 - (($highRiskDecisions / $totalDecisions) * 100))
            : 100;

        // Productivity: task completion rate
        $productivityScore = $totalTasks > 0
            ? ($completedTasks / $totalTasks) * 100
            : 100;

        // Compliance: decisions that passed compliance check
        $complianceFailures = $decisions->filter(fn ($d) => ! $d->compliance_passed)->count();
        $complianceScore = $totalDecisions > 0
            ? max(0, 100 - (($complianceFailures / $totalDecisions) * 100))
            : 100;

        // Reliability: uptime (no failed tasks / total tasks)
        $reliabilityScore = $totalTasks > 0
            ? max(0, 100 - (($failedTasks / $totalTasks) * 80))
            : 100;

        // Trustworthiness: human overrides
        $overriddenDecisions = $decisions->where('human_verdict', 'rejected')->count();
        $trustworthinessScore = $totalDecisions > 0
            ? max(0, 100 - (($overriddenDecisions / $totalDecisions) * 100))
            : 100;

        // Cost savings (estimated)
        $avgTaskCost = $tasks->avg('cost') ?? 0;
        $humanEquivalentCost = $completedTasks * 25; // $25 per human task equivalent
        $agentCost = $tasks->sum('cost');
        $estimatedSavings = max(0, $humanEquivalentCost - $agentCost);
        $costSavingsScore = min(100, ($estimatedSavings / max(1, $humanEquivalentCost)) * 100);

        // User satisfaction: based on decision approvals
        $approvedDecisions = $decisions->where('human_verdict', 'approved')->count();
        $satisfactionScore = $totalDecisions > 0
            ? ($approvedDecisions / max(1, $decisions->where('human_reviewed', true)->count())) * 100
            : 85;

        // Learning rate: improvement in confidence scores over period
        $earlyTasks = $tasks->sortBy('created_at')->take(max(1, (int) ($totalTasks * 0.3)));
        $lateTasks = $tasks->sortByDesc('created_at')->take(max(1, (int) ($totalTasks * 0.3)));
        $earlyAvgConfidence = $earlyTasks->avg('confidence_score') ?? 70;
        $lateAvgConfidence = $lateTasks->avg('confidence_score') ?? 70;
        $learningRate = min(100, max(0, 50 + (($lateAvgConfidence - $earlyAvgConfidence) * 2)));

        $scorecard = AgentScorecard::updateOrCreate(
            [
                'agent_deployment_id' => $deployment->id,
                'period' => $period,
                'period_start' => $start->toDateString(),
            ],
            [
                'organization_id' => $deployment->organization_id,
                'period_end' => $end->toDateString(),
                'accuracy_score' => round($accuracyScore, 2),
                'productivity_score' => round($productivityScore, 2),
                'compliance_score' => round($complianceScore, 2),
                'reliability_score' => round($reliabilityScore, 2),
                'trustworthiness_score' => round($trustworthinessScore, 2),
                'cost_savings_score' => round($costSavingsScore, 2),
                'revenue_impact_score' => 50, // Requires business-level data
                'risk_impact_score' => min(100, 100 - ($highRiskDecisions * 5)),
                'user_satisfaction_score' => round(min(100, $satisfactionScore), 2),
                'learning_rate_score' => round($learningRate, 2),
                'tasks_completed' => $completedTasks,
                'tasks_failed' => $failedTasks,
                'decisions_made' => $totalDecisions,
                'decisions_overridden' => $overriddenDecisions,
                'hallucinations_detected' => $highRiskDecisions,
                'approvals_requested' => $tasks->where('status', 'awaiting_approval')->count(),
                'total_cost' => $tasks->sum('cost'),
                'estimated_savings' => round($estimatedSavings, 2),
                'total_tokens_used' => $tasks->sum('token_count'),
            ]
        );

        // Calculate and set the overall health score
        $scorecard->overall_health_score = $scorecard->calculateOverallScore();
        $scorecard->save();

        return $scorecard;
    }

    private function getPeriodDates(string $period, Carbon $date): array
    {
        return match ($period) {
            'daily' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'weekly' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'quarterly' => [$date->copy()->startOfQuarter(), $date->copy()->endOfQuarter()],
            default => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
        };
    }

    /**
     * Alias for calculatePeriodScorecard() that returns a plain array of scores.
     * Used by tests and external callers that expect an array.
     */
    public function generateScorecard(AgentDeployment $deployment, string $period = 'monthly'): array
    {
        $scorecard = $this->calculatePeriodScorecard($deployment, $period);

        return [
            'accuracy' => $scorecard->accuracy_score,
            'productivity' => $scorecard->productivity_score,
            'compliance' => $scorecard->compliance_score,
            'reliability' => $scorecard->reliability_score,
            'trustworthiness' => $scorecard->trustworthiness_score,
            'cost_savings' => $scorecard->cost_savings_score,
            'revenue_impact' => $scorecard->revenue_impact_score,
            'risk_management' => $scorecard->risk_impact_score,
            'user_satisfaction' => $scorecard->user_satisfaction_score,
            'learning_rate' => $scorecard->learning_rate_score,
            'overall_score' => $scorecard->overall_health_score,
        ];
    }
}
