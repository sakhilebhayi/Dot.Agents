<?php

namespace App\Listeners;

use App\Events\AgentRunContractBreach;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogAgentRunContractBreachAudit implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function handle(AgentRunContractBreach $event): void
    {
        $this->auditService->logAgentAction($event->deployment, 'agent_run_contract_breach', [
            'task_id' => $event->task->id,
            'bound_type' => $event->boundType,
            'measured_value' => $event->measuredValue,
            'limit_value' => $event->limitValue,
        ]);
    }

    public function failed(AgentRunContractBreach $event, Throwable $exception): void
    {
        Log::error('[LogAgentRunContractBreachAudit] Failed to log contract breach audit', [
            'deployment_id' => $event->deployment->id,
            'task_id' => $event->task->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
