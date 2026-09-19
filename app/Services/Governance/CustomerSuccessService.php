<?php

namespace App\Services\Governance;

use App\Models\AgentDeployment;
use App\Models\AgentSession;
use App\Models\AgentTask;
use App\Models\Organization;
use Illuminate\Support\Facades\Cache;

/**
 * CustomerSuccessService
 *
 * Computes the Customer Success Intelligence domain score (0–100) for MEGA V2.
 *
 * Dimensions:
 *  - User Satisfaction (35)  — average task star rating (1–5 → 0–35)
 *  - Task Adoption Rate (25) — ratio of rated tasks to total completed tasks
 *  - Session Retention (25)  — repeat sessions per user (loyalty signal)
 *  - Feedback Volume (15)    — number of rated tasks (data richness signal)
 *
 * MEGA V2 Domain: Business Intelligence → Customer Success (weight: 2%)
 * Target: 80+ (production baseline); 95+ = exceptional customer satisfaction
 */
class CustomerSuccessService
{
    private const CACHE_TTL = 1800; // 30 minutes

    private const LOOKBACK_DAYS = 30;

    /**
     * Calculate the customer success score for an organization.
     *
     * @return array{score: float, dimensions: array, recommendations: array}
     */
    public function calculate(Organization $organization): array
    {
        $cacheKey = "customer_success:{$organization->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organization) {
            return $this->compute($organization);
        });
    }

    /**
     * Invalidate the cached score (call after new ratings or session data).
     */
    public function invalidate(Organization $organization): void
    {
        Cache::forget("customer_success:{$organization->id}");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Core computation
    // ──────────────────────────────────────────────────────────────────────────

    private function compute(Organization $organization): array
    {
        $since = now()->subDays(self::LOOKBACK_DAYS);

        $deploymentIds = AgentDeployment::withoutGlobalScope('organization')
            ->where('organization_id', $organization->id)
            ->pluck('id');

        $dimensions = [];
        $score = 0;

        // ── User Satisfaction (35 pts) ────────────────────────────────────────
        $satisfactionScore = $this->scoreSatisfaction($deploymentIds, $since, $dimensions);
        $score += $satisfactionScore;

        // ── Task Adoption Rate (25 pts) ────────────────────────────────────────
        $adoptionScore = $this->scoreAdoption($deploymentIds, $since, $dimensions);
        $score += $adoptionScore;

        // ── Session Retention (25 pts) ────────────────────────────────────────
        $retentionScore = $this->scoreRetention($organization, $since, $dimensions);
        $score += $retentionScore;

        // ── Feedback Volume (15 pts) ──────────────────────────────────────────
        $volumeScore = $this->scoreFeedbackVolume($deploymentIds, $since, $dimensions);
        $score += $volumeScore;

        return [
            'score' => min(100, round($score, 2)),
            'dimensions' => $dimensions,
            'recommendations' => $this->buildRecommendations($dimensions),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Dimension scorers
    // ──────────────────────────────────────────────────────────────────────────

    private function scoreSatisfaction(mixed $deploymentIds, mixed $since, array &$dimensions): float
    {
        if ($deploymentIds->isEmpty()) {
            $dimensions['user_satisfaction'] = ['score' => 25, 'avg_rating' => null, 'status' => 'no_data'];

            return 25;
        }

        $result = AgentTask::withoutGlobalScope('organization')
            ->whereIn('agent_deployment_id', $deploymentIds)
            ->whereNotNull('rated_at')
            ->where('rated_at', '>=', $since)
            ->selectRaw('AVG(user_rating) as avg_rating, COUNT(*) as rated_count')
            ->first();

        $avgRating = (float) ($result->avg_rating ?? 0);
        $ratedCount = (int) ($result->rated_count ?? 0);

        if ($ratedCount === 0) {
            $dimensions['user_satisfaction'] = ['score' => 25, 'avg_rating' => null, 'status' => 'no_ratings_yet'];

            return 25;
        }

        // Scale 1–5 stars to 0–35 points
        $points = round((($avgRating - 1) / 4) * 35, 2);

        $dimensions['user_satisfaction'] = [
            'score' => $points,
            'avg_rating' => round($avgRating, 2),
            'rated_count' => $ratedCount,
            'status' => $avgRating >= 4.5 ? 'excellent' : ($avgRating >= 3.5 ? 'good' : ($avgRating >= 2.5 ? 'average' : 'poor')),
        ];

        return $points;
    }

    private function scoreAdoption(mixed $deploymentIds, mixed $since, array &$dimensions): float
    {
        if ($deploymentIds->isEmpty()) {
            $dimensions['task_adoption'] = ['score' => 20, 'adoption_rate' => null, 'status' => 'no_data'];

            return 20;
        }

        $totalCompleted = AgentTask::withoutGlobalScope('organization')
            ->whereIn('agent_deployment_id', $deploymentIds)
            ->where('status', 'completed')
            ->where('created_at', '>=', $since)
            ->count();

        $ratedCount = AgentTask::withoutGlobalScope('organization')
            ->whereIn('agent_deployment_id', $deploymentIds)
            ->whereNotNull('rated_at')
            ->where('rated_at', '>=', $since)
            ->count();

        if ($totalCompleted === 0) {
            $dimensions['task_adoption'] = ['score' => 20, 'adoption_rate' => null, 'status' => 'no_tasks'];

            return 20;
        }

        $adoptionRate = ($ratedCount / $totalCompleted) * 100;

        $points = match (true) {
            $adoptionRate >= 50 => 25,   // 50%+ rating adoption = excellent engagement
            $adoptionRate >= 25 => 20,   // 25%+ = good
            $adoptionRate >= 10 => 14,   // 10%+ = acceptable
            $adoptionRate > 0 => 8,      // Some ratings
            default => 5,               // No ratings (no data penalty)
        };

        $dimensions['task_adoption'] = [
            'score' => $points,
            'adoption_rate' => round($adoptionRate, 2),
            'total_completed' => $totalCompleted,
            'rated_count' => $ratedCount,
            'status' => $adoptionRate >= 25 ? 'strong' : ($adoptionRate >= 10 ? 'adequate' : 'weak'),
        ];

        return $points;
    }

    private function scoreRetention(Organization $organization, mixed $since, array &$dimensions): float
    {
        // Count users with 2+ sessions (returning users = retention signal)
        $returningUsers = AgentSession::withoutGlobalScope('organization')
            ->where('organization_id', $organization->id)
            ->where('started_at', '>=', $since)
            ->selectRaw('user_id, COUNT(*) as session_count')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 2')
            ->count();

        $totalUsers = AgentSession::withoutGlobalScope('organization')
            ->where('organization_id', $organization->id)
            ->where('started_at', '>=', $since)
            ->distinct('user_id')
            ->count('user_id');

        if ($totalUsers === 0) {
            $dimensions['session_retention'] = ['score' => 20, 'retention_rate' => null, 'status' => 'no_sessions'];

            return 20;
        }

        $retentionRate = ($returningUsers / $totalUsers) * 100;

        $points = match (true) {
            $retentionRate >= 70 => 25,  // 70%+ returning = excellent retention
            $retentionRate >= 50 => 21,  // 50%+ = good
            $retentionRate >= 30 => 16,  // 30%+ = adequate
            $retentionRate >= 10 => 10,  // 10%+ = low retention
            default => 5,               // <10% = poor retention
        };

        $dimensions['session_retention'] = [
            'score' => $points,
            'retention_rate' => round($retentionRate, 2),
            'returning_users' => $returningUsers,
            'total_users' => $totalUsers,
            'status' => $retentionRate >= 50 ? 'strong' : ($retentionRate >= 30 ? 'adequate' : 'weak'),
        ];

        return $points;
    }

    private function scoreFeedbackVolume(mixed $deploymentIds, mixed $since, array &$dimensions): float
    {
        if ($deploymentIds->isEmpty()) {
            $dimensions['feedback_volume'] = ['score' => 10, 'total_ratings' => 0, 'status' => 'no_data'];

            return 10;
        }

        $totalRatings = AgentTask::withoutGlobalScope('organization')
            ->whereIn('agent_deployment_id', $deploymentIds)
            ->whereNotNull('rated_at')
            ->where('rated_at', '>=', $since)
            ->count();

        $points = match (true) {
            $totalRatings >= 500 => 15,
            $totalRatings >= 100 => 13,
            $totalRatings >= 50 => 11,
            $totalRatings >= 10 => 8,
            $totalRatings >= 1 => 5,
            default => 3,
        };

        $dimensions['feedback_volume'] = [
            'score' => $points,
            'total_ratings' => $totalRatings,
            'status' => $totalRatings >= 100 ? 'rich' : ($totalRatings >= 10 ? 'adequate' : 'sparse'),
        ];

        return $points;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Recommendations
    // ──────────────────────────────────────────────────────────────────────────

    private function buildRecommendations(array $dimensions): array
    {
        $recs = [];

        if (($dimensions['user_satisfaction']['avg_rating'] ?? 5) < 3.5) {
            $recs[] = 'Average satisfaction below 3.5 — review agent response quality and escalation paths';
        }

        if (($dimensions['task_adoption']['adoption_rate'] ?? 100) < 10) {
            $recs[] = 'Task rating adoption below 10% — add in-app prompts to collect feedback post-task';
        }

        if (($dimensions['session_retention']['retention_rate'] ?? 100) < 30) {
            $recs[] = 'Session retention below 30% — improve agent onboarding and response quality';
        }

        if (($dimensions['feedback_volume']['total_ratings'] ?? 999) < 10) {
            $recs[] = 'Low feedback volume — enable task rating UI and notify users to rate completed tasks';
        }

        return $recs;
    }
}
