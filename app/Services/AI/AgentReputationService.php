<?php

namespace App\Services\AI;

use App\Models\AgentSkillApproval;
use App\Models\AgentSkillExecution;
use App\Models\AgentTask;
use App\Support\TaggableCache;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks and computes agent reputation scores from actual execution data.
 *
 * Reputation Dimensions:
 *   - success_rate        : % of tasks/skills completed successfully
 *   - failure_rate        : % of tasks/skills that failed
 *   - approval_rate       : % of approval requests that were granted
 *   - avg_confidence      : average AI confidence across executions
 *   - user_satisfaction   : average user rating from tasks (0-100)
 *
 * Reputation Score: weighted composite (0-100)
 */
class AgentReputationService
{
    private const CACHE_TTL = 1800; // 30 minutes

    /**
     * Compute a reputation profile for a deployment within an organization.
     */
    public function compute(int $deploymentId, int $organizationId): array
    {
        $cacheKey = "agent_reputation_{$deploymentId}_{$organizationId}";

        return TaggableCache::remember(
            ['agent_reputation'],
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->buildProfile($deploymentId, $organizationId)
        );
    }

    /**
     * Invalidate reputation cache for a deployment.
     */
    public function invalidate(int $deploymentId, int $organizationId): void
    {
        TaggableCache::forget(
            ['agent_reputation'],
            "agent_reputation_{$deploymentId}_{$organizationId}"
        );
    }

    private function buildProfile(int $deploymentId, int $organizationId): array
    {
        $taskStats = $this->taskStats($deploymentId, $organizationId);
        $skillStats = $this->skillStats($deploymentId, $organizationId);
        $approvalStats = $this->approvalStats($deploymentId, $organizationId);

        $successRate = $taskStats['success_rate'];
        $failureRate = $taskStats['failure_rate'];
        $approvalRate = $approvalStats['approval_rate'];
        $avgConfidence = $skillStats['avg_confidence'];
        $userSatisfaction = $taskStats['avg_satisfaction'];

        // Composite reputation score (0-100)
        $reputationScore = (int) round(
            ($successRate * 0.35) +
            ((100 - $failureRate) * 0.20) +
            ($approvalRate * 0.15) +
            ($avgConfidence * 0.15) +
            ($userSatisfaction * 0.15)
        );

        return [
            'deployment_id' => $deploymentId,
            'organization_id' => $organizationId,
            'reputation_score' => min(max($reputationScore, 0), 100),
            'reputation_tier' => $this->resolveTier($reputationScore),
            'dimensions' => [
                'success_rate' => round($successRate, 1),
                'failure_rate' => round($failureRate, 1),
                'approval_rate' => round($approvalRate, 1),
                'avg_confidence' => round($avgConfidence, 1),
                'user_satisfaction' => round($userSatisfaction, 1),
            ],
            'data_summary' => [
                'total_tasks' => $taskStats['total'],
                'total_skill_executions' => $skillStats['total'],
                'total_approval_requests' => $approvalStats['total'],
            ],
            'computed_at' => now()->toISOString(),
        ];
    }

    private function taskStats(int $deploymentId, int $organizationId): array
    {
        $tasks = AgentTask::where('agent_deployment_id', $deploymentId)
            ->where('organization_id', $organizationId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(user_rating) as avg_rating
            ")
            ->first();

        $total = (int) ($tasks->total ?? 0);

        if ($total === 0) {
            return ['total' => 0, 'success_rate' => 75.0, 'failure_rate' => 0.0, 'avg_satisfaction' => 75.0];
        }

        $successRate = ($tasks->completed / $total) * 100;
        $failureRate = ($tasks->failed / $total) * 100;
        $avgSatisfaction = $tasks->avg_rating
            ? ($tasks->avg_rating / 5.0) * 100  // normalize 1-5 rating to 0-100
            : 75.0;  // default when no ratings

        return [
            'total' => $total,
            'success_rate' => $successRate,
            'failure_rate' => $failureRate,
            'avg_satisfaction' => $avgSatisfaction,
        ];
    }

    private function skillStats(int $deploymentId, int $organizationId): array
    {
        $stats = AgentSkillExecution::where('agent_deployment_id', $deploymentId)
            ->where('organization_id', $organizationId)
            ->whereNotNull('confidence')
            ->selectRaw('COUNT(*) as total, AVG(confidence) as avg_confidence')
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'avg_confidence' => (float) ($stats->avg_confidence ?? 75.0),
        ];
    }

    private function approvalStats(int $deploymentId, int $organizationId): array
    {
        $stats = AgentSkillApproval::where('agent_deployment_id', $deploymentId)
            ->where('organization_id', $organizationId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
            ")
            ->first();

        $total = (int) ($stats->total ?? 0);
        $approvalRate = $total > 0
            ? (($stats->approved / $total) * 100)
            : 100.0;  // no rejections = 100% approval rate

        return [
            'total' => $total,
            'approval_rate' => $approvalRate,
        ];
    }

    private function resolveTier(int $score): string
    {
        return match (true) {
            $score >= 90 => 'elite',
            $score >= 75 => 'trusted',
            $score >= 60 => 'standard',
            $score >= 40 => 'provisional',
            default => 'under_review',
        };
    }
}
