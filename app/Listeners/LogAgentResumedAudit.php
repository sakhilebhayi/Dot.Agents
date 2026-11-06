<?php

namespace App\Listeners;

use App\Events\AgentResumed;
use App\Jobs\SendPlatformNotification;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogAgentResumedAudit implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function handle(AgentResumed $event): void
    {
        $deployment = $event->deployment;

        $this->auditService->logUserAction(
            event: 'agent.resumed',
            description: "Deployment {$deployment->display_name} was resumed",
            subject: $deployment,
        );

        SendPlatformNotification::toAdmins(
            organizationId: $deployment->organization_id,
            type: 'agent_resumed',
            title: "Agent Resumed: {$deployment->display_name}",
            message: 'The deployment has been resumed and will process new tasks.',
            severity: 'info',
            data: ['deployment_id' => $deployment->id],
            actionUrl: "/my-agents/{$deployment->id}"
        );
    }

    public function failed(AgentResumed $event, Throwable $exception): void
    {
        Log::warning('[LogAgentResumedAudit] Failed to log agent resume', [
            'deployment_id' => $event->deployment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
