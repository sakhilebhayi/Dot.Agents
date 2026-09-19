<?php

namespace App\Skills\Governance;

use App\Skills\BaseSkill;
use App\Skills\DTOs\SkillResult;

/**
 * Risk Assessment Skill (Layer 4 — Self-Governance)
 *
 * Evaluates the risk profile of a proposed task or action before execution.
 * Scores five risk dimensions:
 *   1. Impact — scope and magnitude of the action
 *   2. Reversibility — can the action be undone?
 *   3. Data exposure — does the task touch sensitive/PII data?
 *   4. External action — does execution reach outside the platform?
 *   5. Confidence gap — how far is the agent's confidence from its threshold?
 *
 * Returns an aggregate risk score, risk level (low/medium/high/critical),
 * and a flag indicating whether human approval is recommended.
 */
class RiskAssessmentSkill extends BaseSkill
{
    public function key(): string
    {
        return 'risk-assessment';
    }

    public function layer(): string
    {
        return 'governance';
    }

    public function execute(array $input, array $context = []): SkillResult
    {
        $taskDesc = $input['task_description'] ?? $input['task'] ?? '';
        $output = $input['output'] ?? [];
        $deployment = $context['deployment'] ?? null;

        $dimensions = [
            'impact' => $this->assessImpact($taskDesc, $output),
            'reversibility' => $this->assessReversibility($taskDesc),
            'data_exposure' => $this->assessDataExposure($taskDesc),
            'external_action' => $this->assessExternalAction($taskDesc),
            'confidence_gap' => $this->assessConfidenceGap($output, $deployment),
        ];

        $riskScore = $this->clamp(
            array_sum(array_column($dimensions, 'score')) / count($dimensions)
        );

        $riskLevel = match (true) {
            $riskScore >= 70 => 'critical',
            $riskScore >= 50 => 'high',
            $riskScore >= 30 => 'medium',
            default => 'low',
        };

        $findings = array_map(
            fn ($dim, $key) => "{$key}: {$dim['finding']}",
            array_filter($dimensions, fn ($d) => ($d['score'] ?? 0) >= 50),
            array_keys(array_filter($dimensions, fn ($d) => ($d['score'] ?? 0) >= 50))
        );

        $recommendations = array_values(array_filter(array_map(
            fn ($d) => ($d['score'] ?? 0) >= 50 ? ($d['recommendation'] ?? null) : null,
            $dimensions
        )));

        return SkillResult::completed(
            [
                'risk_score' => round($riskScore, 1),
                'risk_level' => $riskLevel,
                'dimensions' => $dimensions,
                'requires_approval' => $riskScore >= 50,
            ],
            $this->clamp(100 - $riskScore),
            $findings,
            $recommendations
        );
    }

    // ── Risk dimensions ──────────────────────────────────

    private function assessImpact(string $task, array $output): array
    {
        $highImpactMarkers = ['delete', 'remove', 'terminate', 'cancel', 'send', 'deploy', 'transfer', 'publish', 'execute', 'shutdown'];
        $baseScore = 20.0;

        if ($this->countKeywords($task, $highImpactMarkers) > 0) {
            $baseScore = 65.0;
        }

        $impactFromOutput = (float) ($output['impact_score'] ?? 0);
        $score = $this->clamp(max($baseScore, $impactFromOutput * 0.7));

        return [
            'score' => $score,
            'finding' => "Impact score: {$score}/100",
            'recommendation' => 'Review scope and magnitude before execution',
        ];
    }

    private function assessReversibility(string $task): array
    {
        $irreversible = ['delete', 'drop', 'terminate', 'cancel', 'remove', 'purge', 'clear', 'wipe', 'destroy'];
        $score = $this->countKeywords($task, $irreversible) > 0 ? 80.0 : 10.0;

        return [
            'score' => $score,
            'finding' => $score >= 50 ? 'Action appears irreversible' : 'Action appears reversible',
            'recommendation' => 'Ensure a rollback or undo plan exists',
        ];
    }

    private function assessDataExposure(string $task): array
    {
        $dataMarkers = ['pii', 'personal', 'sensitive', 'confidential', 'private', 'customer data', 'employee', 'health', 'financial record', 'ssn', 'password'];
        $score = $this->countKeywords($task, $dataMarkers) > 0 ? 75.0 : 10.0;

        return [
            'score' => $score,
            'finding' => $score >= 50 ? 'Task involves sensitive data' : 'No obvious sensitive data markers',
            'recommendation' => 'Apply data minimisation and access controls',
        ];
    }

    private function assessExternalAction(string $task): array
    {
        $externalMarkers = ['api', 'webhook', 'external', 'third-party', 'email', 'notification', 'payment', 'sms', 'slack', 'teams'];
        $score = $this->countKeywords($task, $externalMarkers) > 0 ? 60.0 : 10.0;

        return [
            'score' => $score,
            'finding' => $score >= 50 ? 'Task reaches external systems' : 'No external system interaction detected',
            'recommendation' => 'Verify target external system availability and permissions',
        ];
    }

    private function assessConfidenceGap(array $output, mixed $deployment): array
    {
        $confidence = (float) ($output['confidence'] ?? 75.0);
        $threshold = (float) ($deployment->confidence_threshold ?? 75.0);
        $gap = max(0.0, $threshold - $confidence);
        $score = $this->clamp($gap * 2.0);

        return [
            'score' => $score,
            'finding' => "Confidence gap: {$gap}% below threshold ({$threshold}%)",
            'recommendation' => 'Gather more evidence or escalate for human review',
        ];
    }
}
