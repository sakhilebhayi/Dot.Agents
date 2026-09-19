<?php

namespace App\Listeners;

use App\Events\SkillExecuted;
use App\Jobs\SendPlatformNotification;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Logs a governance audit entry for every skill execution and emits a
 * high-risk notification when confidence drops below the deployment threshold.
 */
class AuditSkillExecution implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function handle(SkillExecuted $event): void
    {
        $execution = $event->execution;
        $deployment = $execution->deployment;

        // Governance audit record
        if ($deployment) {
            $this->auditService->logAgentAction(
                $deployment,
                'skill.executed',
                [
                    'skill_id' => $execution->skill_id,
                    'skill_name' => $execution->skill?->name,
                    'status' => $execution->status,
                    'confidence' => $execution->confidence,
                    'duration_ms' => $execution->duration_ms,
                ]
            );
        }

        // Alert admins when confidence is dangerously low on a completed execution
        $confidence = (float) ($execution->confidence ?? 100.0);
        $threshold = (float) ($deployment->confidence_threshold ?? 75.0);

        if ($execution->status === 'completed' && $confidence < ($threshold * 0.7)) {
            SendPlatformNotification::toAdmins(
                organizationId: $execution->organization_id,
                type: 'low_confidence_skill',
                title: "Low Confidence Execution: {$deployment?->display_name}",
                message: "Skill '{$execution->skill?->name}' completed with only ".round($confidence).'% confidence (threshold: '.round($threshold).'%).',
                severity: 'warning',
                data: [
                    'execution_id' => $execution->id,
                    'confidence' => $confidence,
                    'threshold' => $threshold,
                ],
                actionUrl: "/agents/{$deployment?->id}/scorecard"
            );
        }
    }

    public function failed(SkillExecuted $event, Throwable $exception): void
    {
        Log::warning('[AuditSkillExecution] Failed', [
            'execution_id' => $event->execution->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
