<?php

namespace App\Events;

use App\Models\SocialAccount;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SocialAccountConnected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SocialAccount $account,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organizations.{$this->account->organization_id}"),
        ];
    }
}
