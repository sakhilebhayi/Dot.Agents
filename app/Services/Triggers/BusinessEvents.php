<?php

namespace App\Services\Triggers;

use App\Jobs\FireBusinessEvent;
use App\Models\AgentRun;

/** Something happened that a business may have set work to follow. Work started this way never raises another event. */
class BusinessEvents
{
    public function raise(int $organizationId, string $event, string $key, string $summary): void
    {
        FireBusinessEvent::dispatch($organizationId, $event, $key, $summary);
    }

    public function runEnded(AgentRun $run): void
    {
        if ($run->status === AgentRun::FAILED && $run->trigger_type !== 'event') {
            $this->raise($run->organization_id, 'run.failed', $run->uuid, "{$run->deployment?->name} failed: {$run->error_message}");
        }
    }
}
