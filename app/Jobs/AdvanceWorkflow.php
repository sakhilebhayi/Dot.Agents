<?php

namespace App\Jobs;

use App\Services\Workflows\WorkflowConductor;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AdvanceWorkflow implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $executionId, public readonly int $organizationId)
    {
        $this->onConnection(config('agents.queue_connection'))->onQueue(config('agents.queue'));
    }

    public function handle(WorkflowConductor $conductor): void
    {
        TenantContext::forOrganization($this->organizationId, fn () => $conductor->advance($this->executionId));
    }
}
