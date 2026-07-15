<?php

namespace App\Events;

use App\Models\Organization;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SocialCredentialsSaved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Organization $organization,
        public readonly string $platform,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organizations.{$this->organization->id}"),
        ];
    }
}
