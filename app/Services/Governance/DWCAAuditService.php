<?php

namespace App\Services\Governance;

use App\Models\Agent;
use App\Models\AgentDeployment;
use App\Services\Governance\Audit\DWCAPhaseRunner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Digital Workforce Certification Audit — Orchestrator (DWCA v1.0)
 *
 * This service owns the audit lifecycle: scheduling phases, aggregating
 * results, computing composite scores, and emitting the final report.
 * Business logic for each phase lives in the dedicated Phase* strategy
 * classes under App\Services\Governance\Audit\.
 *
 * Certification Levels:
 *   1 = Experimental
 *   2 = Internal Use
 *   3 = Production Ready
 *   4 = Enterprise Ready
 *   5 = Enterprise Certified
 *   6 = World Class Digital Workforce
 */
class DWCAAuditService
{
    public const MIN_MARKETPLACE_MATURITY = 6;

    public const ENTERPRISE_READY_THRESHOLD = 80;

    public const ENTERPRISE_CERTIFIED_THRESHOLD = 90;

    public function __construct(
        private readonly AuditService $auditService,
        private readonly DWCAPhaseRunner $phaseRunner,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Run the full 15-phase DWCA for all agents in an organization.
     */
    public function auditOrganization(int $organizationId): array
    {
        Log::info('[DWCA] Starting Digital Workforce Certification Audit', [
            'organization_id' => $organizationId,
        ]);

        $deployments = AgentDeployment::where('organization_id', $organizationId)
            ->with(['agent.skills', 'latestScorecard', 'tasks'])
            ->get();

        $agentResults = $deployments->map(fn ($d) => $this->auditDeployment($d))->all();
        $report = $this->compileReport($organizationId, $agentResults);

        foreach ($agentResults as $result) {
            if (isset($result['agent_id'])) {
                Agent::where('id', $result['agent_id'])->update([
                    'dwca_certification_level' => $result['certification_level'],
                    'dwca_certified_at' => now(),
                    'maturity_level' => $result['maturity_level'],
                ]);
            }
        }

        $this->auditService->logUserAction(
            event: 'dwca.audit.completed',
            description: "DWCA v1.0 audit completed for organization {$organizationId}",
            data: [
                'total_agents' => count($agentResults),
                'certified_count' => collect($agentResults)->where('certification_level', '>=', 4)->count(),
                'composite_score' => $report['composite_score'],
                'certification_level' => $report['enterprise_certification_level'],
            ],
        );

        Cache::put("dwca_audit_{$organizationId}", $report, now()->addHours(1));

        return $report;
    }

    /**
     * Audit a single deployment across all DWCA phase strategies.
     */
    public function auditDeployment(AgentDeployment $deployment): array
    {
        $agent = $deployment->agent;

        $phases = $this->phaseRunner->run($deployment);
        $compositeScore = $this->computeCompositeScore($this->phaseRunner->scores($phases));
        $certificationLevel = $this->resolveCertificationLevel($compositeScore);
        $maturityLevel = $this->resolveMaturityLevel(
            $deployment,
            $phases['phase2_skill_audit'],
            $phases['phase6_governance'],
            $phases['phase4_quality'],
        );

        return [
            'deployment_id' => $deployment->id,
            'agent_id' => $agent?->id,
            'agent_name' => $deployment->name ?? $agent->name ?? 'Unknown',
            'agent_slug' => $agent?->slug,
            'composite_score' => $compositeScore,
            'certification_level' => $certificationLevel,
            'certification_label' => $this->certificationLabel($certificationLevel),
            'maturity_level' => $maturityLevel,
            'maturity_label' => $this->maturityLabel($maturityLevel),
            'marketplace_eligible' => $maturityLevel >= self::MIN_MARKETPLACE_MATURITY,
            'phases' => $phases,
            'failures' => $this->collectFailures(array_values($phases)),
        ];
    }

    // ── Report compilation ────────────────────────────────────────────────────

    private function compileReport(int $organizationId, array $agentResults): array
    {
        $compositeScores = collect($agentResults)->pluck('composite_score');
        $overallScore = $compositeScores->isNotEmpty() ? (int) $compositeScores->avg() : 0;
        $certificationLevel = $this->resolveCertificationLevel($overallScore);

        $sorted = collect($agentResults)->sortByDesc('composite_score');

        return [
            'audit_version' => 'DWCA v1.0',
            'audited_at' => now()->toIso8601String(),
            'organization_id' => $organizationId,
            'total_agents_audited' => count($agentResults),
            'composite_score' => $overallScore,
            'enterprise_certification_level' => $certificationLevel,
            'certification_label' => $this->certificationLabel($certificationLevel),
            'dimension_scores' => $this->computeDimensionScores($agentResults),
            'agent_results' => $agentResults,
            'top_agents' => $sorted->take(5)->values()->all(),
            'weakest_agents' => $sorted->reverse()->take(5)->values()->all(),
            'marketplace_blocked' => collect($agentResults)->filter(fn ($r) => ! $r['marketplace_eligible'])->values()->all(),
            'certified_count' => collect($agentResults)->where('certification_level', '>=', 4)->count(),
            'experimental_count' => collect($agentResults)->where('certification_level', 1)->count(),
            'remediation_roadmap' => $this->buildRemediationRoadmap($agentResults),
        ];
    }

    private function computeDimensionScores(array $agentResults): array
    {
        $phaseKeys = [
            'discovery' => 'phase1_discovery',
            'skills' => 'phase2_skill_audit',
            'quality' => 'phase4_quality',
            'governance' => 'phase6_governance',
            'delusion' => 'phase7_delusion',
            'memory' => 'phase8_memory',
            'performance' => 'phase12_performance',
            'scorecard' => 'phase13_scorecard',
        ];

        return array_map(
            fn ($key) => (int) (collect($agentResults)->pluck("phases.{$key}.score")->filter()->avg() ?? 0),
            $phaseKeys,
        );
    }

    private function buildRemediationRoadmap(array $agentResults): array
    {
        $roadmap = [];
        foreach ($agentResults as $result) {
            foreach ($result['failures'] ?? [] as $failure) {
                $roadmap[] = [
                    'agent' => $result['agent_name'],
                    'priority' => $this->remediationPriority($failure),
                    'finding' => $failure,
                    'recommendation' => $this->remediationRecommendation($failure),
                ];
            }
        }

        usort($roadmap, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $roadmap;
    }

    // ── Scoring helpers ───────────────────────────────────────────────────────

    private function computeCompositeScore(array $phaseScores): int
    {
        $weights = [0.15, 0.20, 0.20, 0.20, 0.10, 0.05, 0.05, 0.05];
        $weighted = 0.0;
        foreach ($phaseScores as $i => $score) {
            $weighted += $score * ($weights[$i] ?? 0.05);
        }

        return (int) round($weighted);
    }

    private function resolveCertificationLevel(int $score): int
    {
        return match (true) {
            $score >= 95 => 6,
            $score >= 90 => 5,
            $score >= 80 => 4,
            $score >= 65 => 3,
            $score >= 50 => 2,
            default => 1,
        };
    }

    private function certificationLabel(int $level): string
    {
        return match ($level) {
            6 => 'World Class Digital Workforce',
            5 => 'Enterprise Certified',
            4 => 'Enterprise Ready',
            3 => 'Production Ready',
            2 => 'Internal Use',
            default => 'Experimental',
        };
    }

    private function resolveMaturityLevel(AgentDeployment $deployment, array $phase2, array $phase6, array $phase4): int
    {
        if ($phase4['score'] >= 90 && $phase6['score'] >= 90 && $phase2['score'] >= 90) {
            return in_array($deployment->deployment_mode, ['autonomous', 'executive_approval']) ? 7 : 6;
        }
        if ($phase6['score'] >= 80 && $phase2['score'] >= 80) {
            return 5;
        }
        if ($phase6['score'] >= 60) {
            return 4;
        }
        if ($phase2['checks']['has_assigned_skills'] ?? false) {
            return 3;
        }
        if (! empty($deployment->agent->skills)) {
            return 2;
        }

        return $deployment->agent_id ? 1 : 0;
    }

    private function maturityLabel(int $level): string
    {
        return match ($level) {
            10 => 'Autonomous Business Unit',
            9 => 'Digital Executive',
            8 => 'Digital Department',
            7 => 'Self-Optimizing',
            6 => 'Enterprise Certified',
            5 => 'Autonomous',
            4 => 'Multi-agent Capable',
            3 => 'Governed',
            2 => 'Skills Executable',
            1 => 'Skills Defined',
            default => 'Registered Only',
        };
    }

    private function collectFailures(array $phases): array
    {
        $failures = [];
        foreach ($phases as $phase) {
            foreach ($phase['failures'] ?? [] as $failure) {
                $failures[] = "[{$phase['phase']}] {$failure}";
            }
        }

        return $failures;
    }

    private function remediationPriority(string $failure): int
    {
        return match (true) {
            str_contains($failure, 'security') || str_contains($failure, 'injection') => 1,
            str_contains($failure, 'governance') || str_contains($failure, 'audit') => 2,
            str_contains($failure, 'skill') || str_contains($failure, 'delusion') => 3,
            str_contains($failure, 'scorecard') => 4,
            default => 5,
        };
    }

    private function remediationRecommendation(string $failure): string
    {
        return match (true) {
            str_contains($failure, 'has_assigned_skills') => 'Assign at least one skill via AssignSkillToDeploymentAction.',
            str_contains($failure, 'scorecard') => 'Run GenerateAgentScorecard::dispatch($deployment) to create initial scorecard.',
            str_contains($failure, 'confidence_threshold') => 'Set a confidence_threshold > 0 on the AgentDeployment record.',
            str_contains($failure, 'input_hash') => 'Upgrade platform to capture input_hash (SHA-256) in DecisionLog.',
            default => "Review and remediate: {$failure}",
        };
    }
}
