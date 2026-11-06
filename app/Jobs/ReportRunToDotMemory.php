<?php

namespace App\Jobs;

use App\Models\AgentRun;
use App\Services\Memory\DotMemoryClient;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Tells Dot.Memory what an agent did. Best effort: nothing here can affect the work, and the client never throws. */
class ReportRunToDotMemory implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $runId, public readonly int $organizationId)
    {
        $this->onConnection(config('agents.queue_connection'))->onQueue(config('agents.queue'));
    }

    public function handle(DotMemoryClient $memory): void
    {
        TenantContext::forOrganization($this->organizationId, function () use ($memory) {
            $run = AgentRun::find($this->runId);

            if ($run !== null) {
                $memory->recordAction($run->uuid, 'agent_run', (string) $run->id, $run->trigger_type, $run->status === AgentRun::COMPLETED ? 'succeeded' : 'failed');
            }
        });
    }
}
