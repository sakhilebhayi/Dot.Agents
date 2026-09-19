<?php

namespace App\Services\AI;

use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentMessage;
use App\Models\AgentScorecard;
use App\Models\AgentTask;
use App\Models\EnterpriseHealthScore;
use App\Models\OrganizationTwin;
use App\Services\Governance\AuditService;
use App\Services\Governance\EnterpriseConstitutionService;
use App\Services\Governance\ScorecardService;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Cache;

/**
 * Enterprise Brain Service — Dot.OS™ Adaptive Enterprise Consciousness v2.0
 *
 * The Enterprise Brain is the cognitive layer that governs all AI activity
 * within an organization. It consists of 6 intelligence cores, each responsible
 * for a specific domain of enterprise cognition:
 *
 *   Core 1 — Strategic Intelligence   : Mission alignment, OKR tracking, strategic coherence
 *   Core 2 — Economic Intelligence    : Financial health, ROI, cost optimization
 *   Core 3 — Operational Intelligence : Workflow analysis, bottleneck detection, efficiency
 *   Core 4 — Governance Intelligence  : Compliance, risk management, policy enforcement
 *   Core 5 — Learning Intelligence    : Continuous improvement, pattern recognition, adaptation
 *   Core 6 — Predictive Intelligence  : Trend analysis, risk forecasting, opportunity detection
 */
class EnterpriseBrainService
{
    public function __construct(
        private readonly EnterpriseConstitutionService $constitutionService,
        private readonly AuditService $auditService,
        private readonly ScorecardService $scorecardService,
        private readonly EnterpriseBrainScorer $scorer,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // CORE 1: Strategic Intelligence
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Evaluate whether an agent action aligns with the organization's strategic mission.
     * Called before executing any strategic-tier agent task.
     */
    public function evaluateStrategicAlignment(AgentDeployment $deployment, string $action): array
    {
        $alignment = $this->constitutionService->validateAlignment(
            $deployment->organization_id,
            $action
        );

        return [
            'core' => 'strategic',
            'aligned' => $alignment['aligned'],
            'risk_level' => $alignment['risk_level'],
            'violations' => $alignment['violations'],
            'recommendation' => $alignment['aligned']
                ? 'Proceed — action aligns with organizational constitution'
                : 'Escalate — constitutional violations detected',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORE 2: Economic Intelligence
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assess the financial health and AI ROI for an organization.
     * Uses the Digital Twin's budget allocation and task cost tracking.
     */
    public function assessEconomicHealth(int $organizationId): array
    {
        $twin = OrganizationTwin::where('organization_id', $organizationId)->latest()->first();

        $monthlyCost = $twin->monthly_ai_spend_usd ?? 0;
        $roi = $twin->estimated_ai_roi ?? 0;

        $score = $this->scorer->computeEconomicScore($roi);

        return [
            'core' => 'economic',
            'health_score' => $score,
            'monthly_ai_spend_usd' => $monthlyCost,
            'estimated_roi_pct' => $roi,
            'status' => $this->scorer->computeEconomicStatus($score),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORE 3: Operational Intelligence
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Detect operational bottlenecks from agent task latency and failure patterns.
     */
    public function detectBottlenecks(int $organizationId, int $lookbackDays = 7): array
    {
        $since = now()->subDays($lookbackDays);

        // Aggregate task performance by agent deployment
        $taskStats = AgentTask::whereHas('deployment', fn ($q) => $q->where('organization_id', $organizationId))
            ->where('created_at', '>=', $since)
            ->selectRaw("agent_deployment_id, COUNT(*) as task_count, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failures")
            ->groupBy('agent_deployment_id')
            ->get()
            ->keyBy('agent_deployment_id');

        // latency_ms is tracked per-message (real LLM call latency), not on
        // AgentTask — join through AgentSession to group by deployment.
        $latencyStats = AgentMessage::withoutGlobalScope('organization')
            ->join('agent_sessions', 'agent_messages.session_id', '=', 'agent_sessions.id')
            ->where('agent_messages.organization_id', $organizationId)
            ->where('agent_messages.created_at', '>=', $since)
            ->whereNotNull('agent_messages.latency_ms')
            ->selectRaw('agent_sessions.agent_deployment_id as agent_deployment_id, AVG(agent_messages.latency_ms) as avg_latency')
            ->groupBy('agent_sessions.agent_deployment_id')
            ->get()
            ->keyBy('agent_deployment_id');

        $bottlenecks = [];
        foreach ($taskStats as $deploymentId => $stat) {
            $avgLatency = (float) ($latencyStats->get($deploymentId)->avg_latency ?? 0);
            $taskCount = (int) $stat->task_count;
            $failures = (int) $stat->failures;

            if ($avgLatency > 5000 || ($taskCount > 0 && ($failures / $taskCount) > 0.1)) {
                $bottlenecks[] = [
                    'deployment_id' => $deploymentId,
                    'avg_latency_ms' => round($avgLatency),
                    'failure_rate' => $taskCount > 0 ? round(($failures / $taskCount) * 100, 1) : 0,
                    'severity' => $avgLatency > 10000 ? 'critical' : 'warning',
                ];
            }
        }

        return [
            'core' => 'operational',
            'bottlenecks_detected' => count($bottlenecks),
            'bottlenecks' => $bottlenecks,
            'analysis_period_days' => $lookbackDays,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORE 4: Governance Intelligence
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calculate overall governance health — compliance, policy adherence, risk posture.
     */
    public function assessGovernanceHealth(int $organizationId): array
    {
        $cacheKey = "brain_governance_{$organizationId}";

        return Cache::remember($cacheKey, 900, function () use ($organizationId) {
            $riskScore = $this->constitutionService->getRiskAppetiteScore($organizationId);

            // Count pending approvals and overdue tasks as governance risk indicators
            $pendingApprovals = AgentApproval::where('organization_id', $organizationId)
                ->where('status', 'pending')
                ->count();

            $overdueApprovals = AgentApproval::where('organization_id', $organizationId)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subHours(24))
                ->count();

            $governanceScore = $this->scorer->computeGovernanceScore($pendingApprovals, $overdueApprovals);

            return [
                'core' => 'governance',
                'health_score' => $governanceScore,
                'pending_approvals' => $pendingApprovals,
                'overdue_approvals' => $overdueApprovals,
                'risk_appetite_score' => $riskScore,
                'status' => $this->scorer->computeGovernanceStatus($governanceScore),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORE 5: Learning Intelligence
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate improvement recommendations by analyzing scorecard trends.
     * The Learning Core identifies what's working and what needs intervention.
     */
    public function generateLearningInsights(int $organizationId): array
    {
        $deployments = AgentDeployment::where('organization_id', $organizationId)
            ->with('agent')
            ->where('status', 'active')
            ->get();

        $insights = [];
        $improvingAgents = 0;
        $decliningAgents = 0;

        foreach ($deployments as $deployment) {
            // Get two recent scorecard periods for trend analysis
            $scores = AgentScorecard::where('agent_deployment_id', $deployment->id)
                ->orderByDesc('period_end')
                ->take(2)
                ->pluck('composite_score')
                ->toArray();

            if (count($scores) >= 2) {
                $trend = $scores[0] - $scores[1];
                if ($trend > 5) {
                    $improvingAgents++;
                } elseif ($trend < -5) {
                    $decliningAgents++;
                    $insights[] = [
                        'agent' => $deployment->agent->name ?? "Deployment #{$deployment->id}",
                        'type' => 'declining_performance',
                        'score_drop' => round(abs($trend), 1),
                        'recommendation' => 'Review task quality, confidence thresholds, and knowledge gaps',
                    ];
                }
            }
        }

        return [
            'core' => 'learning',
            'total_active_agents' => $deployments->count(),
            'improving_agents' => $improvingAgents,
            'declining_agents' => $decliningAgents,
            'insights' => array_slice($insights, 0, 10),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORE 6: Predictive Intelligence
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Forecast risks and opportunities over the next 30 days based on current trends.
     *
     * @deprecated Delegated to EnterpriseBrainOrchestrator — use that class directly for new code.
     */
    public function generatePredictions(int $organizationId): array
    {
        return Container::getInstance()
            ->make(EnterpriseBrainOrchestrator::class)
            ->generatePredictions($organizationId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ENTERPRISE HEALTH SCORE (composite across all cores)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compute the composite Enterprise Health Score across all 8 domains.
     * Stores a daily snapshot in enterprise_health_scores.
     *
     * @deprecated Delegated to EnterpriseBrainOrchestrator — use that class directly for new code.
     */
    public function computeEnterpriseHealth(int $organizationId): EnterpriseHealthScore
    {
        return Container::getInstance()
            ->make(EnterpriseBrainOrchestrator::class)
            ->computeEnterpriseHealth($organizationId);
    }
}
