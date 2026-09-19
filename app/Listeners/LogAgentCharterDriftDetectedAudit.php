<?php

namespace App\Listeners;

use App\Events\AgentCharterDriftDetected;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogAgentCharterDriftDetectedAudit implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function handle(AgentCharterDriftDetected $event): void
    {
        $this->auditService->logAgentAction($event->deployment, 'agent_charter_drift_detected', [
            'description' => $event->description,
        ]);
    }

    public function failed(AgentCharterDriftDetected $event, Throwable $exception): void
    {
        Log::error('[LogAgentCharterDriftDetectedAudit] Failed to log charter drift audit', [
            'deployment_id' => $event->deployment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
