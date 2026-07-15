<?php

namespace App\Events;

use App\Models\SocialConnectionSettings;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConnectionSettingsSaved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SocialConnectionSettings $settings,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organizations.{$this->settings->organization_id}"),
        ];
    }
}
