<?php

namespace App\Skills\Meta;

use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\AuditLog;
use App\Models\DecisionLog;
use App\Skills\BaseSkill;
use App\Skills\DTOs\SkillResult;
use Carbon\Carbon;

/**
 * Agent Auditor Skill (Meta-Agent Layer)
 *
 * Performs a governance compliance audit on an agent deployment over a
 * configurable look-back period.
 *
 * Audit checks:
 *   1. Tasks have audit log entries                  (traceability)
 *   2. No high-confidence tasks without evidence      (accuracy governance)
 *   3. High-delusion-risk tasks were reviewed         (hallucination control)
 *   4. Approvals were not bypassed                    (authorization)
 *   5. Decision logs present for completed tasks      (decision governance)
 *
 * Returns a compliance score (0-100) and detailed finding per check.
 */
class AgentAuditorSkill extends BaseSkill
{
    public function key(): string
    {
        return 'agent-auditor';
    }

    public function layer(): string
    {
        return 'meta';
    }

    /**
     * Input keys:
     *   deployment_id  – int (or falls back to $context['deployment']->id)
     *   period_hours   – look-back window in hours (default 24)
     */
    public function execute(array $input, array $context = []): SkillResult
    {
        $deployment = $context['deployment'] ?? null;
        $deploymentId = $input['deployment_id']
            ?? ($deployment instanceof AgentDeployment ? $deployment->id : null);

        if (! $deploymentId) {
            return SkillResult::failed('No deployment_id provided for audit');
        }

        $target = AgentDeployment::find($deploymentId);
        if (! $target) {
            return SkillResult::failed("Deployment #{$deploymentId} not found");
        }

        $periodHours = (int) ($input['period_hours'] ?? 24);
        $since = now()->subHours($periodHours);

        $checks = [
            'tasks_have_audit_logs' => $this->checkTasksHaveAuditLogs($target, $since),
            'high_confidence_justified' => $this->checkHighConfidenceJustified($target, $since),
            'delusion_risks_reviewed' => $this->checkDelusionRisksReviewed($target, $since),
            'no_bypassed_approvals' => $this->checkNoBypassed($target, $since),
            'decision_logs_present' => $this->checkDecisionLogs($target, $since),
        ];

        $passed = array_filter($checks, fn ($c) => $c['passed']);
        $failed = array_filter($checks, fn ($c) => ! $c['passed']);
        $complianceScore = $this->clamp(count($passed) / max(1, count($checks)) * 100);

        $findings = array_values(array_filter(
            array_map(fn ($c) => $c['finding'] ?? null, $failed)
        ));

        return SkillResult::completed(
            [
                'compliance_score' => round($complianceScore, 1),
                'is_compliant' => $complianceScore >= 80,
                'period_hours' => $periodHours,
                'checks' => $checks,
                'passed_count' => count($passed),
                'failed_count' => count($failed),
                'deployment_id' => $deploymentId,
                'audited_at' => now()->toIso8601String(),
            ],
            100.0,
            $findings,
            $complianceScore < 80 ? ['Address failed audit checks to maintain governance compliance'] : []
        );
    }

    // ── Audit checks ─────────────────────────────────────

    private function checkTasksHaveAuditLogs(AgentDeployment $deployment, Carbon $since): array
    {
        $taskCount = AgentTask::where('agent_deployment_id', $deployment->id)
            ->where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->count();

        $auditCount = AuditLog::where('agent_deployment_id', $deployment->id)
            ->where('created_at', '>=', $since)
            ->count();

        $passed = $taskCount === 0 || $auditCount >= ($taskCount * 0.8); // 80% audit coverage acceptable

        return [
            'passed' => $passed,
            'finding' => $passed ? null : "Audit log gap: {$taskCount} completed tasks but only {$auditCount} audit entries",
        ];
    }

    private function checkHighConfidenceJustified(AgentDeployment $deployment, Carbon $since): array
    {
        $highConfNoEvidence = AgentTask::where('agent_deployment_id', $deployment->id)
            ->where('created_at', '>=', $since)
            ->where('confidence_score', '>=', 85)
            ->where(fn ($q) => $q->whereNull('output_data')
                ->orWhereRaw("json_extract(output_data, '$.evidence') IS NULL")
            )
            ->count();

        $passed = $highConfNoEvidence === 0;

        return [
            'passed' => $passed,
            'finding' => $passed ? null : "{$highConfNoEvidence} high-confidence task(s) lack evidence backing",
        ];
    }

    private function checkDelusionRisksReviewed(AgentDeployment $deployment, Carbon $since): array
    {
        $highRiskUnreviewed = AgentTask::where('agent_deployment_id', $deployment->id)
            ->where('created_at', '>=', $since)
            ->where('delusion_risk_score', '>=', 60)
            ->where('status', 'completed')   // completed without approval gate means it wasn't reviewed
            ->whereDoesntHave('approval')
            ->count();

        $passed = $highRiskUnreviewed === 0;

        return [
            'passed' => $passed,
            'finding' => $passed ? null : "{$highRiskUnreviewed} high-delusion-risk task(s) completed without human review",
        ];
    }

    private function checkNoBypassed(AgentDeployment $deployment, Carbon $since): array
    {
        // Tasks that were awaiting approval but completed anyway
        $bypassed = AgentTask::where('agent_deployment_id', $deployment->id)
            ->where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->where('confidence_score', '<', $deployment->confidence_threshold)
            ->whereDoesntHave('approval')
            ->count();

        $passed = $bypassed === 0;

        return [
            'passed' => $passed,
            'finding' => $passed ? null : "{$bypassed} task(s) completed below confidence threshold without approval",
        ];
    }

    private function checkDecisionLogs(AgentDeployment $deployment, Carbon $since): array
    {
        $taskCount = AgentTask::where('agent_deployment_id', $deployment->id)
            ->where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->count();

        $decisionCount = DecisionLog::where('agent_deployment_id', $deployment->id)
            ->where('created_at', '>=', $since)
            ->count();

        $passed = $taskCount === 0 || $decisionCount >= ($taskCount * 0.9);

        return [
            'passed' => $passed,
            'finding' => $passed ? null : "Missing decision logs: {$taskCount} tasks but only {$decisionCount} decision log entries",
        ];
    }
}
