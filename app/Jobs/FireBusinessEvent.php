<?php

namespace App\Jobs;

use App\Models\AgentTrigger;
use App\Services\Triggers\TriggerFirer;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FireBusinessEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $organizationId, public readonly string $event, public readonly string $key, public readonly string $summary)
    {
        $this->onConnection(config('agents.queue_connection'))->onQueue(config('agents.queue'));
    }

    public function handle(TriggerFirer $firer): void
    {
        TenantContext::forOrganization($this->organizationId, function () use ($firer) {
            AgentTrigger::where('organization_id', $this->organizationId)->where('kind', AgentTrigger::EVENT)->where('is_active', true)
                ->where('settings->event', $this->event)
                ->each(fn (AgentTrigger $trigger) => $firer->fire($trigger, $this->key, $this->summary));
        });
    }
}
