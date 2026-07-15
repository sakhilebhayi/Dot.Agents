<?php

namespace App\Listeners;

use App\Events\KillSwitchActivated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogKillSwitchActivated implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'critical';

    public int $tries = 5;

    public function handle(KillSwitchActivated $event): void
    {
        Log::critical('[Security] Kill switch activated', [
            'scope' => $event->scope,
            'organization_id' => $event->organizationId,
            'reason' => $event->reason,
            'actor_id' => $event->actorId,
            'metadata' => $event->metadata,
        ]);
    }
}
