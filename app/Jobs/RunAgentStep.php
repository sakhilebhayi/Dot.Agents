<?php

namespace App\Jobs;

use App\Services\Runs\RunStepper;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * One step of one run: one model turn or one tool call, then the next step queues itself. Between steps no process
 * exists, so a person can take a week to answer an approval and it costs nothing.
 */
class RunAgentStep implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 170;

    public function __construct(public readonly int $runId, public readonly int $organizationId)
    {
        $this->onConnection(config('agents.queue_connection'))->onQueue(config('agents.queue'));
    }

    public function handle(RunStepper $stepper): void
    {
        TenantContext::forOrganization($this->organizationId, fn () => $stepper->advance($this->runId));
    }
}
