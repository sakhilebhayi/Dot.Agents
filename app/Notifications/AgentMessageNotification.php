<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** A message an agent sent a person, labelled as coming from the agent. */
class AgentMessageNotification extends Notification
{
    public function __construct(
        private readonly string $agent,
        private readonly string $subject,
        private readonly string $message,
        private readonly string $run,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'agent_message', 'agent' => $this->agent, 'subject' => $this->subject, 'message' => $this->message, 'run' => $this->run];
    }
}
