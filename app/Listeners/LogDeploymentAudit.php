<?php

namespace App\Listeners;

use App\Events\AgentDeployed;
use App\Jobs\SendPlatformNotification;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogDeploymentAudit implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function handle(AgentDeployed $event): void
    {
        $deployment = $event->deployment;

        $this->auditService->logAgentAction($deployment, 'agent_deployed', [
            'agent_id' => $deployment->agent_id,
            'agent_name' => $deployment->agent->name ?? 'unknown',
            'deployment_mode' => $deployment->deployment_mode,
            'deployed_by' => $deployment->deployed_by,
            'deployed_at' => now()->toIso8601String(),
        ]);

        // Notify org admins about the new deployment
        SendPlatformNotification::toAdmins(
            organizationId: $deployment->organization_id,
            type: 'agent_deployed',
            title: "Agent Deployed: {$deployment->display_name}",
            message: "A new agent has been deployed in {$deployment->deployment_mode} mode.",
            severity: 'info',
            data: ['deployment_id' => $deployment->id],
            actionUrl: "/my-agents/{$deployment->id}"
        );
    }

    public function failed(AgentDeployed $event, Throwable $exception): void
    {
        Log::error('[LogDeploymentAudit] Failed to log deployment audit', [
            'deployment_id' => $event->deployment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
