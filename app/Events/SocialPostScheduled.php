<?php

namespace App\Events;

use App\Models\SocialPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SocialPostScheduled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SocialPost $post,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organizations.{$this->post->organization_id}"),
        ];
    }
}
