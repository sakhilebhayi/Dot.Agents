<?php

namespace App\Actions\Skills;

use App\Events\SkillScoreRecorded;
use App\Models\AgentDeployment;
use App\Models\AgentSkillScore;
use Illuminate\Support\Facades\Gate;

class RecordSkillScoreAction
{
    /**
     * Upsert monthly skill performance scores for a deployment.
     * Called after each execution completes.
     *
     * Security: verifies the deployment belongs to the given organization
     * (domain integrity guard) so scores cannot be written for foreign deployments.
     */
    public function execute(
        int $skillId,
        int $deploymentId,
        int $organizationId,
        string $executionStatus,  // completed|failed|blocked|skipped
        ?float $confidence = null,
        ?int $durationMs = null,
    ): void {
        // Domain integrity — prevent cross-org scorecard pollution.
        $deployment = AgentDeployment::withoutGlobalScope('organization')
            ->where('id', $deploymentId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // If running in an HTTP context, authorize via policy (no-op in queue).
        if (auth()->check()) {
            Gate::authorize('update', $deployment);
        }

        $period = now()->format('Y-m');

        $score = AgentSkillScore::firstOrCreate(
            [
                'skill_id' => $skillId,
                'agent_deployment_id' => $deploymentId,
                'organization_id' => $organizationId,
                'period' => $period,
            ],
            [
                'total_executions' => 0,
                'successful_executions' => 0,
                'failed_executions' => 0,
                'blocked_executions' => 0,
            ]
        );

        // Increment counters
        $score->increment('total_executions');

        match ($executionStatus) {
            'completed' => $score->increment('successful_executions'),
            'failed' => $score->increment('failed_executions'),
            'skipped' => $score->increment('blocked_executions'),
            default => null,
        };

        // Refresh for recalculation
        $score->refresh();

        $updates = [];

        // Recalculate success rate
        if ($score->total_executions > 0) {
            $updates['success_rate'] = round(
                ($score->successful_executions / $score->total_executions) * 100,
                2
            );
        }

        // Update running average confidence
        if ($confidence !== null) {
            $updates['avg_confidence'] = $score->avg_confidence
                ? round(($score->avg_confidence + $confidence) / 2, 2)
                : $confidence;
        }

        // Update running average duration
        if ($durationMs !== null) {
            $updates['avg_duration_ms'] = $score->avg_duration_ms
                ? round(($score->avg_duration_ms + $durationMs) / 2, 2)
                : $durationMs;
        }

        if (! empty($updates)) {
            $score->update($updates);
        }

        event(new SkillScoreRecorded($skillId, $deploymentId, $organizationId, $executionStatus, $confidence));
    }
}
