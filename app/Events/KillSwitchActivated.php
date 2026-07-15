<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KillSwitchActivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $scope  deployment|workflows|organization
     * @param  array  $metadata  scope-specific payload (e.g. deployment_id, executions_aborted)
     */
    public function __construct(
        public readonly string $scope,
        public readonly int $organizationId,
        public readonly string $reason,
        public readonly ?int $actorId,
        public readonly array $metadata = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organizations.{$this->organizationId}"),
        ];
    }
}
