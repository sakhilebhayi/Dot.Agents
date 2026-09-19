<?php

namespace App\Services\AI;

use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\AgentTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Scores an agent across 5 certification dimensions and issues a trust tier.
 *
 * Trust Tiers:
 *   - Platinum  (90-100) — Fully autonomous, no override needed
 *   - Gold      (80-89)  — High trust, light oversight
 *   - Silver    (65-79)  — Standard oversight required
 *   - Bronze    (50-64)  — Enhanced oversight required
 *   - Uncertified (<50) — Cannot be deployed in autonomous mode
 */
class AgentCertificationService
{
    private const CACHE_TTL_SECONDS = 3600; // 1 hour

    /**
     * Calculate and cache a certification score for an agent.
     */
    public function certify(Agent $agent, int $organizationId): array
    {
        $cacheKey = "agent_cert_{$agent->id}_{$organizationId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($agent, $organizationId) {
            return $this->computeCertification($agent, $organizationId);
        });
    }

    /**
     * Force re-certification (clears cache).
     */
    public function recertify(Agent $agent, int $organizationId): array
    {
        Cache::forget("agent_cert_{$agent->id}_{$organizationId}");

        return $this->certify($agent, $organizationId);
    }

    private function computeCertification(Agent $agent, int $organizationId): array
    {
        $scores = [
            'accuracy' => $this->scoreAccuracy($agent, $organizationId),
            'reliability' => $this->scoreReliability($agent, $organizationId),
            'security' => $this->scoreSecurity($agent),
            'governance' => $this->scoreGovernance($agent),
            'performance' => $this->scorePerformance($agent, $organizationId),
            'risk' => $this->scoreRisk($agent),
            'compliance' => $this->scoreCompliance($agent),
        ];

        $certificationScore = (int) round(
            ($scores['accuracy'] * 0.25) +
            ($scores['reliability'] * 0.20) +
            ($scores['security'] * 0.18) +
            ($scores['governance'] * 0.12) +
            ($scores['performance'] * 0.10) +
            ($scores['risk'] * 0.10) +
            ($scores['compliance'] * 0.05)
        );

        $tier = $this->resolveTier($certificationScore);

        $result = [
            'certification_score' => $certificationScore,
            'trust_tier' => $tier,
            'dimension_scores' => $scores,
            'certified_at' => now()->toISOString(),
            'recommended_modes' => $this->recommendedModes($tier),
            'requires_human_review' => $certificationScore < 65,
        ];

        Log::info('AgentCertificationService: certification computed', [
            'agent_id' => $agent->id,
            'organization_id' => $organizationId,
            'certification_score' => $certificationScore,
            'trust_tier' => $tier,
        ]);

        $agent->update([
            'certification_score' => $certificationScore,
            'trust_score' => $certificationScore,
            'trust_tier' => $tier,
            'certified_at' => now(),
        ]);

        return $result;
    }

    private function scoreAccuracy(Agent $agent, int $organizationId): int
    {
        $tasks = AgentTask::where('agent_deployment_id', function ($query) use ($agent, $organizationId) {
            $query->select('id')
                ->from('agent_deployments')
                ->where('agent_id', $agent->id)
                ->where('organization_id', $organizationId);
        })
            ->where('status', 'completed')
            ->whereNotNull('accuracy_score')
            ->latest()
            ->limit(100)
            ->get();

        if ($tasks->isEmpty()) {
            // Fallback to platform-wide accuracy score from agent model
            return (int) ($agent->accuracy_score ?? 70);
        }

        return (int) round($tasks->avg('accuracy_score') ?? 70);
    }

    private function scoreReliability(Agent $agent, int $organizationId): int
    {
        $total = AgentTask::whereHas('deployment', fn ($q) => $q
            ->where('agent_id', $agent->id)
            ->where('organization_id', $organizationId)
        )->count();

        if ($total === 0) {
            return (int) ($agent->reliability_score ?? 70);
        }

        $failed = AgentTask::whereHas('deployment', fn ($q) => $q
            ->where('agent_id', $agent->id)
            ->where('organization_id', $organizationId)
        )->where('status', 'failed')->count();

        $successRate = (($total - $failed) / $total) * 100;

        return (int) round($successRate);
    }

    private function scoreSecurity(Agent $agent): int
    {
        $score = 60; // baseline

        // Verified agents get a security boost
        if ($agent->is_verified) {
            $score += 20;
        }

        // Risk controls defined
        if (! empty($agent->risk_controls)) {
            $score += 10;
        }

        // Limitations documented
        if (! empty($agent->limitations)) {
            $score += 10;
        }

        return min($score, 100);
    }

    private function scoreGovernance(Agent $agent): int
    {
        $score = 50; // baseline

        // Decision framework documented
        if (! empty($agent->decision_framework)) {
            $score += 20;
        }

        // KPIs defined
        if (! empty($agent->kpis)) {
            $score += 15;
        }

        // Goals and objectives defined
        if (! empty($agent->goals) && ! empty($agent->objectives)) {
            $score += 15;
        }

        return min($score, 100);
    }

    private function scorePerformance(Agent $agent, int $organizationId): int
    {
        // latency_ms is tracked per-message (real LLM call latency), not on
        // AgentTask (which only has coarser, minute-granularity durations) —
        // go through AgentMessage -> AgentSession -> AgentDeployment.
        $avgLatency = AgentMessage::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->whereHas('session.deployment', fn ($q) => $q->where('agent_id', $agent->id))
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');

        if (! $avgLatency) {
            return 75; // default if no data
        }

        // Score based on latency: <1s=100, <3s=80, <5s=60, <10s=40, >10s=20
        return match (true) {
            $avgLatency < 1000 => 100,
            $avgLatency < 3000 => 80,
            $avgLatency < 5000 => 60,
            $avgLatency < 10000 => 40,
            default => 20,
        };
    }

    /**
     * Risk Score — evaluates if the agent has defined risk management controls.
     * Higher score = lower operational risk.
     */
    private function scoreRisk(Agent $agent): int
    {
        $score = 40; // baseline

        // Risk controls documented
        if (! empty($agent->risk_controls)) {
            $score += 20;
        }

        // Limitations clearly documented
        if (! empty($agent->limitations)) {
            $score += 15;
        }

        // Confidence threshold set (prevents unchecked autonomous actions)
        if (! empty($agent->model_config['confidence_threshold'])) {
            $score += 15;
        }

        // Low-risk tier deployment mode
        if ($agent->default_deployment_mode === 'advisory') {
            $score += 10;
        }

        return min($score, 100);
    }

    /**
     * Compliance Score — evaluates regulatory/audit readiness.
     * Higher score = more audit-ready for enterprise procurement.
     */
    private function scoreCompliance(Agent $agent): int
    {
        $score = 30; // baseline

        // Certifications provided
        if (! empty($agent->certifications)) {
            $score += 25;
        }

        // Required permissions declared
        if (! empty($agent->knowledge_areas)) {
            $score += 15;
        }

        // Decision framework documented (SOX, GDPR, etc.)
        if (! empty($agent->decision_framework)) {
            $score += 20;
        }

        // Is verified by platform
        if ($agent->is_verified) {
            $score += 10;
        }

        return min($score, 100);
    }

    private function resolveTier(int $score): string
    {
        return match (true) {
            $score >= 90 => 'platinum',
            $score >= 80 => 'gold',
            $score >= 65 => 'silver',
            $score >= 50 => 'bronze',
            default => 'uncertified',
        };
    }

    private function recommendedModes(string $tier): array
    {
        return match ($tier) {
            'platinum' => ['autonomous', 'semi-autonomous', 'advisory', 'executive_approval'],
            'gold' => ['semi-autonomous', 'advisory', 'executive_approval'],
            'silver' => ['advisory', 'executive_approval'],
            'bronze' => ['advisory'],
            default => [], // no autonomous modes for uncertified agents
        };
    }
}
