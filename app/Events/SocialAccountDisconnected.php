<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SocialAccountDisconnected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $organizationId,
        public readonly string $platform,
        public readonly string $accountName,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organizations.{$this->organizationId}"),
        ];
    }
}
