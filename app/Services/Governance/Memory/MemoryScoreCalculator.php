<?php

namespace App\Services\Governance\Memory;

use App\Models\AgentMemory;
use App\Models\DecisionLog;
use App\Models\KnowledgeArticle;
use Illuminate\Support\Collection;

/**
 * MemoryScoreCalculator
 *
 * Computes all five scoring dimensions for the Organizational Memory Score:
 *  - Knowledge Retention     (25 pts)
 *  - Memory Quality          (25 pts)
 *  - Decision History        (20 pts)
 *  - Root Cause Retention    (15 pts)
 *  - Learning Velocity       (15 pts)
 *
 * Also builds the knowledge graph summary and generates recommendations.
 *
 * This class is purely computational — no caching, no side-effects.
 * Extracted from OrganizationalMemoryService.
 */
class MemoryScoreCalculator
{
    private const LESSON_CATEGORIES = ['lesson', 'decision', 'knowledge'];

    /**
     * Compute the full memory score for an organization.
     */
    public function compute(int $orgId): array
    {
        $retention = $this->scoreKnowledgeRetention($orgId);
        $quality = $this->scoreMemoryQuality($orgId);
        $history = $this->scoreDecisionHistory($orgId);
        $rootCause = $this->scoreRootCauseRetention($orgId);
        $velocity = $this->scoreLearningVelocity($orgId);

        $total = round(
            $retention['score'] + $quality['score'] + $history['score']
            + $rootCause['score'] + $velocity['score'],
            2
        );

        return [
            'score' => $total,
            'gate_pass' => $total >= 85,
            'target' => 95,
            'organization_id' => $orgId,
            'computed_at' => now()->toIso8601String(),
            'dimensions' => compact('retention', 'quality', 'history', 'rootCause', 'velocity'),
            'recommendations' => $this->recommendations($retention, $quality, $history, $rootCause, $velocity),
        ];
    }

    /**
     * Build the knowledge graph summary for an organization.
     */
    public function buildKnowledgeGraphSummary(int $orgId): array
    {
        $memoriesByType = AgentMemory::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->selectRaw('memory_type, memory_category, count(*) as count, avg(importance_score) as avg_importance')
            ->groupBy('memory_type', 'memory_category')
            ->get()
            ->groupBy('memory_type')
            ->map(fn (Collection $group) => $group->keyBy('memory_category')->map(fn ($row) => [
                'count' => $row->count,
                'avg_importance' => round($row->avg_importance, 2),
            ]));

        $articlesByCategory = KnowledgeArticle::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_published', true)
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        return [
            'organization_id' => $orgId,
            'memories_by_type' => $memoriesByType,
            'articles_by_category' => $articlesByCategory,
            'total_memories' => AgentMemory::withoutGlobalScope('organization')->where('organization_id', $orgId)->where('is_active', true)->count(),
            'total_articles' => KnowledgeArticle::withoutGlobalScope('organization')->where('organization_id', $orgId)->where('is_published', true)->count(),
            'total_decisions' => DecisionLog::withoutGlobalScope('organization')->where('organization_id', $orgId)->count(),
            'built_at' => now()->toIso8601String(),
        ];
    }

    // ── Dimension scorers ──────────────────────────────────────────────────────

    private function scoreKnowledgeRetention(int $orgId): array
    {
        $decisions = DecisionLog::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)->count();

        $articles = KnowledgeArticle::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_published', true)->count();

        $ratio = $decisions > 0 ? min(1, ($articles / $decisions) * 10) : ($articles > 0 ? 1 : 0);

        return [
            'score' => round($ratio * 25, 2),
            'max' => 25,
            'decisions' => $decisions,
            'articles' => $articles,
            'ratio' => $decisions > 0 ? round($articles / $decisions, 4) : 0,
            'target_ratio' => 0.1,
        ];
    }

    private function scoreMemoryQuality(int $orgId): array
    {
        $total = AgentMemory::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)->where('is_active', true)->count();

        if ($total === 0) {
            return ['score' => 0, 'max' => 25, 'note' => 'No active memories'];
        }

        $verified = AgentMemory::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)->where('is_verified', true)->count();

        $highImportance = AgentMemory::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)->where('importance_score', '>=', 70)->count();

        $recentlyAccessed = AgentMemory::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('last_accessed_at', '>=', now()->subDays(30))->count();

        $combined = ($verified / $total * 0.4) + ($highImportance / $total * 0.35) + ($recentlyAccessed / $total * 0.25);

        return [
            'score' => round($combined * 25, 2),
            'max' => 25,
            'total' => $total,
            'verified' => $verified,
            'high_importance' => $highImportance,
            'recently_accessed' => $recentlyAccessed,
            'verified_rate' => round($verified / $total * 100, 2),
        ];
    }

    private function scoreDecisionHistory(int $orgId): array
    {
        $total = DecisionLog::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)->count();

        if ($total === 0) {
            return ['score' => 20, 'max' => 20, 'note' => 'No decisions yet'];
        }

        $complete = DecisionLog::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereNotNull('final_outcome')
            ->whereNotNull('outcome_notes')->count();

        $rate = $complete / $total;

        return [
            'score' => round($rate * 20, 2),
            'max' => 20,
            'total' => $total,
            'complete' => $complete,
            'coverage' => round($rate * 100, 2),
        ];
    }

    private function scoreRootCauseRetention(int $orgId): array
    {
        $lessonMemories = AgentMemory::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereIn('memory_category', self::LESSON_CATEGORIES)
            ->where('is_active', true)->count();

        $decisions = DecisionLog::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)->count();

        $targetLessons = max(1, (int) ($decisions / 5));
        $ratio = min(1, $lessonMemories / $targetLessons);

        return [
            'score' => round($ratio * 15, 2),
            'max' => 15,
            'lesson_memories' => $lessonMemories,
            'target' => $targetLessons,
            'coverage' => round($ratio * 100, 2),
        ];
    }

    private function scoreLearningVelocity(int $orgId): array
    {
        $recentVerified = AgentMemory::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_verified', true)
            ->where('created_at', '>=', now()->subDays(14))->count();

        $target = 5;
        $ratio = min(1, $recentVerified / $target);

        return [
            'score' => round($ratio * 15, 2),
            'max' => 15,
            'new_verified_14d' => $recentVerified,
            'target' => $target,
            'velocity_rate' => round($ratio * 100, 2),
        ];
    }

    private function recommendations(array ...$dimensions): array
    {
        $recs = [];

        foreach ($dimensions as $dim) {
            if (isset($dim['ratio']) && $dim['ratio'] < 0.05) {
                $recs[] = 'Knowledge distillation is low. Create more KB articles from completed decisions.';
            }
            if (isset($dim['verified_rate']) && $dim['verified_rate'] < 70) {
                $recs[] = "Only {$dim['verified_rate']}% of memories are verified. Schedule a memory verification review.";
            }
            if (isset($dim['coverage']) && isset($dim['lesson_memories']) && $dim['coverage'] < 50) {
                $recs[] = "Root cause retention is low ({$dim['coverage']}%). Record lessons from failures as agent memories.";
            }
            if (isset($dim['velocity_rate']) && $dim['velocity_rate'] < 60) {
                $recs[] = 'Learning velocity is slow. Target 5+ new verified memories per fortnight.';
            }
        }

        return array_unique($recs);
    }
}
