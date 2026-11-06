<?php

namespace App\Services\Runs;

use App\Jobs\RunAgentStep;
use App\Models\AgentRun;
use App\Support\TenantContext;
use Throwable;

/**
 * The queue worker starts once a minute, which is a long wait for a reply. So after the web response has gone, the same
 * PHP process takes the first steps of a new run. The lease means it can never collide with the queue worker, and if
 * this never happens the queue simply picks the run up.
 */
class RunKick
{
    public function __construct(private readonly RunStepper $stepper) {}

    public function after(AgentRun $run): void
    {
        $seconds = (int) config('agents.kick_seconds');

        if ($seconds <= 0 || app()->runningInConsole()) {
            return;
        }

        $runId = $run->id;
        $organizationId = $run->organization_id;

        app()->terminating(function () use ($runId, $organizationId, $seconds) {
            $stopAt = microtime(true) + $seconds;

            try {
                TenantContext::forOrganization($organizationId, function () use ($runId, $organizationId, $stopAt) {
                    // One step at a time. The lease and the journal make it safe to stop anywhere.
                    do {
                        $next = $this->stepper->advance($runId, dispatchNext: false);
                    } while ($next === RunStepper::CONTINUE && microtime(true) < $stopAt);

                    if ($next === RunStepper::CONTINUE) {
                        RunAgentStep::dispatch($runId, $organizationId);
                    }
                });
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
