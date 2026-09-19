<?php

namespace App\Services\Governance;

use App\Models\Organization;
use App\Services\Governance\DataTrust\DataGovernanceScorer;
use App\Services\Governance\DataTrust\DataQualityScorer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * DataTrustScoreService — thin coordinator.
 * Data scoring delegated to DataQualityScorer and DataGovernanceScorer.
 */
class DataTrustScoreService
{
    private const CACHE_TTL = 1800;

    public function __construct(
        private readonly DataQualityScorer $qualityScorer,
        private readonly DataGovernanceScorer $governanceScorer,
    ) {}

    public function calculate(Organization $organization): array
    {
        $cacheKey = "data_trust_score:{$organization->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organization) {
            return $this->compute($organization);
        });
    }

    public function invalidate(Organization $organization): void
    {
        Cache::forget("data_trust_score:{$organization->id}");
    }

    private function compute(Organization $organization): array
    {
        $orgId = $organization->id;

        $completeness = $this->qualityScorer->scoreCompleteness($orgId);
        $integrity = $this->qualityScorer->scoreIntegrity($orgId);
        $freshness = $this->qualityScorer->scoreFreshness($orgId);
        $memory = $this->governanceScorer->scoreMemoryQuality($orgId);
        $decisions = $this->governanceScorer->scoreDecisionQuality($orgId);

        $total = round(
            $completeness['score'] + $integrity['score'] + $freshness['score']
            + $memory['score'] + $decisions['score'],
            2
        );

        $result = [
            'score' => $total,
            'gate_pass' => $total >= 90,
            'target' => 98,
            'organization_id' => $orgId,
            'computed_at' => now()->toIso8601String(),
            'dimensions' => compact('completeness', 'integrity', 'freshness', 'memory', 'decisions'),
            'recommendations' => $this->governanceScorer->generateRecommendations(
                $completeness, $integrity, $freshness, $memory, $decisions
            ),
        ];

        if ($total < 90) {
            Log::warning('[DataTrust] Score below production gate', [
                'organization_id' => $orgId,
                'score' => $total,
                'gate' => 90,
            ]);
        }

        return $result;
    }
}
