<?php

namespace App\Notifications;

use App\Models\AgentApproval;
use Illuminate\Notifications\Notification;

/** Tells the person who deployed an agent how an approval was answered. */
class ApprovalAnsweredNotification extends Notification
{
    public function __construct(private readonly AgentApproval $approval) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_answered',
            'approval_id' => $this->approval->id,
            'title' => $this->approval->title,
            'answer' => $this->approval->status,
            'url' => route('governance.approvals', ['approval' => $this->approval->uuid]),
        ];
    }
}
