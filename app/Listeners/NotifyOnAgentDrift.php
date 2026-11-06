<?php

namespace App\Listeners;

use App\Events\AgentDriftDetected;
use App\Jobs\SendPlatformNotification;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyOnAgentDrift implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function handle(AgentDriftDetected $event): void
    {
        $deployment = $event->deployment;
        $severity = $event->severity;

        // Log security event
        $this->auditService->logSecurityEvent(
            organizationId: $deployment->organization_id,
            eventType: 'agent_drift',
            severity: $severity,
            title: "Agent Drift Detected: {$deployment->display_name}",
            description: "Drift type: {$event->driftType}. Severity: {$severity}.",
            data: $event->details,
            deploymentId: $deployment->id
        );

        // Notify admins with appropriate urgency
        SendPlatformNotification::toAdmins(
            organizationId: $deployment->organization_id,
            type: 'agent_drift',
            title: "⚠️ Agent Drift: {$deployment->display_name}",
            message: "Drift type '{$event->driftType}' detected. Review agent performance.",
            severity: $severity === 'critical' ? 'error' : 'warning',
            data: array_merge(['deployment_id' => $deployment->id], $event->details),
            actionUrl: "/my-agents/{$deployment->id}/scorecard"
        );

        // Auto-escalate critical drift: pause the deployment
        if ($severity === 'critical' && $event->driftType === 'high_delusion_risk') {
            $deployment->update(['status' => 'suspended']);

            $this->auditService->logAgentAction($deployment, 'auto_suspended_drift', [
                'drift_type' => $event->driftType,
                'severity' => $severity,
            ]);
        }
    }

    public function failed(AgentDriftDetected $event, Throwable $exception): void
    {
        Log::error('[NotifyOnAgentDrift] Failed to process drift notification', [
            'deployment_id' => $event->deployment->id,
            'drift_type' => $event->driftType,
            'error' => $exception->getMessage(),
        ]);
    }
}
