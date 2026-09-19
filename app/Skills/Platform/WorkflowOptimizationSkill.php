<?php

namespace App\Skills\Platform;

use App\Models\AgentWorkflow;
use App\Models\WorkflowExecution;
use App\Skills\BaseSkill;
use App\Skills\DTOs\SkillResult;

/**
 * Workflow Optimization Skill (Layer 5 — Platform Intelligence)
 *
 * Analyses recent workflow execution history to surface:
 *   – High failure rates
 *   – Slow execution paths (bottleneck nodes)
 *   – Elevated per-run costs
 *   – Parallelisation opportunities
 *
 * Can be invoked for a single workflow or all workflows in an organisation.
 */
class WorkflowOptimizationSkill extends BaseSkill
{
    public function key(): string
    {
        return 'workflow-optimization';
    }

    public function layer(): string
    {
        return 'platform';
    }

    /**
     * Input keys:
     *   workflow_id     – integer (optional; analyses all org workflows if omitted)
     *   lookback_days   – how many days of history to analyse (default 30)
     */
    public function execute(array $input, array $context = []): SkillResult
    {
        $workflowId = isset($input['workflow_id']) ? (int) $input['workflow_id'] : null;
        $orgId = $context['deployment']->organization_id ?? (int) session('current_organization_id');
        $lookbackDays = (int) ($input['lookback_days'] ?? 30);

        if ($workflowId) {
            return $this->analyseWorkflow($workflowId, $lookbackDays);
        }

        return $this->analyseAllWorkflows($orgId, $lookbackDays);
    }

    // ── Single workflow analysis ──────────────────────────

    private function analyseWorkflow(int $workflowId, int $lookbackDays): SkillResult
    {
        $executions = WorkflowExecution::where('workflow_id', $workflowId)
            ->where('started_at', '>=', now()->subDays($lookbackDays))
            ->get();

        if ($executions->isEmpty()) {
            return SkillResult::skipped("No executions found for workflow #{$workflowId} in the last {$lookbackDays} days");
        }

        $completed = $executions->where('status', 'completed');
        $failed = $executions->where('status', 'failed');

        $avgDurationSec = $completed->average(fn ($e) => $e->started_at && $e->completed_at
                ? $e->started_at->diffInSeconds($e->completed_at)
                : null
        ) ?? 0;

        $failureRate = $executions->count() > 0
            ? round($failed->count() / $executions->count() * 100, 1)
            : 0;

        $avgCost = $executions->average('total_cost') ?? 0;

        $recommendations = $this->buildRecommendations($failureRate, $avgDurationSec, $avgCost);

        $healthScore = $this->clamp(100 - $failureRate - ($avgDurationSec > 300 ? 15 : 0) - ($avgCost > 0.5 ? 10 : 0));

        return SkillResult::completed(
            [
                'workflow_id' => $workflowId,
                'period_days' => $lookbackDays,
                'total_executions' => $executions->count(),
                'completed' => $completed->count(),
                'failed' => $failed->count(),
                'failure_rate_pct' => $failureRate,
                'avg_duration_sec' => round($avgDurationSec, 1),
                'avg_cost_usd' => round($avgCost, 4),
                'health_score' => round($healthScore, 1),
            ],
            92.0,
            $failureRate > 20 ? ["High failure rate: {$failureRate}%"] : [],
            $recommendations
        );
    }

    // ── All-org analysis ──────────────────────────────────

    private function analyseAllWorkflows(int $orgId, int $lookbackDays): SkillResult
    {
        $workflows = AgentWorkflow::where('organization_id', $orgId)
            ->where('status', 'active')
            ->withCount(['executions as total_runs' => fn ($q) => $q->where('started_at', '>=', now()->subDays($lookbackDays))])
            ->withCount(['executions as failed_runs' => fn ($q) => $q->where('status', 'failed')->where('started_at', '>=', now()->subDays($lookbackDays))])
            ->get(['id', 'name']);

        $summary = $workflows->map(fn ($wf) => [
            'id' => $wf->id,
            'name' => $wf->name,
            'total_runs' => $wf->total_runs,
            'failed_runs' => $wf->failed_runs,
            'failure_rate' => $wf->total_runs > 0
                ? round($wf->failed_runs / $wf->total_runs * 100, 1)
                : 0,
        ])->sortByDesc('failure_rate')->values()->toArray();

        $worstWorkflow = $workflows->sortByDesc('failed_runs')->first();

        return SkillResult::completed(
            [
                'org_id' => $orgId,
                'period_days' => $lookbackDays,
                'workflow_count' => $workflows->count(),
                'workflows' => $summary,
            ],
            90.0,
            $worstWorkflow && $worstWorkflow->failed_runs > 0
                ? ["Workflow '{$worstWorkflow->name}' has the highest failure count ({$worstWorkflow->failed_runs} failures)"]
                : [],
            ['Review workflows with failure rate > 20% first', 'Enable parallel node execution for long-running workflows']
        );
    }

    // ── Helpers ──────────────────────────────────────────

    private function buildRecommendations(float $failureRate, float $avgDuration, float $avgCost): array
    {
        $recs = [];

        if ($failureRate > 20) {
            $recs[] = "Failure rate ({$failureRate}%) is high — add error handling nodes and retry logic";
        }
        if ($avgDuration > 300) {
            $recs[] = 'Average execution exceeds 5 minutes — identify bottleneck nodes and enable parallel execution';
        }
        if ($avgCost > 0.50) {
            $recs[] = "Average run cost (\${$avgCost}) is elevated — cache intermediate results and reduce redundant AI calls";
        }
        if (empty($recs)) {
            $recs[] = 'Workflow health looks good — no critical issues detected';
        }

        return $recs;
    }
}
