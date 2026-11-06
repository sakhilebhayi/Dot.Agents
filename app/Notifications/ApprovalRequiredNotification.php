<?php

namespace App\Notifications;

use App\Mail\ApprovalRequestedEmail;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ApprovalRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AgentApproval $approval,
        private readonly AgentDeployment $deployment,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): ApprovalRequestedEmail
    {
        return (new ApprovalRequestedEmail($this->approval, $this->deployment))->to($notifiable->email);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_required',
            'approval_id' => $this->approval->id,
            'deployment_id' => $this->deployment->id,
            'deployment_name' => $this->deployment->name,
            'task_description' => $this->approval->description,
            'confidence_score' => $this->approval->confidence_score,
            'expires_at' => $this->approval->expires_at?->toISOString(),
            'url' => route('governance.approvals', ['approval' => $this->approval->uuid]),
        ];
    }
}
