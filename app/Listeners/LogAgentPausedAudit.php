<?php

namespace App\Listeners;

use App\Events\AgentPaused;
use App\Jobs\SendPlatformNotification;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogAgentPausedAudit implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function handle(AgentPaused $event): void
    {
        $deployment = $event->deployment;

        $this->auditService->logUserAction(
            event: 'agent.paused',
            description: "Deployment {$deployment->display_name} was paused",
            subject: $deployment,
        );

        SendPlatformNotification::toAdmins(
            organizationId: $deployment->organization_id,
            type: 'agent_paused',
            title: "Agent Paused: {$deployment->display_name}",
            message: 'The deployment has been paused and will not process new tasks.',
            severity: 'warning',
            data: ['deployment_id' => $deployment->id],
            actionUrl: "/my-agents/{$deployment->id}"
        );
    }

    public function failed(AgentPaused $event, Throwable $exception): void
    {
        Log::warning('[LogAgentPausedAudit] Failed', [
            'deployment_id' => $event->deployment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
