<?php

namespace App\Listeners;

use App\Events\ApprovalProcessed;
use App\Notifications\ApprovalAnsweredNotification;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyOnApprovalProcessed implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(ApprovalProcessed $event): void
    {
        $approval = $event->approval;
        $deployment = $approval->deployment;

        if (! $deployment) {
            return;
        }

        // Notify the deployer that their approval was processed
        $deployer = $deployment->deployedBy;

        if ($deployer && $approval->status !== 'pending') {
            $deployer->notify(new ApprovalAnsweredNotification($approval));
        }

        // Log the approval outcome via audit
        app(AuditService::class)->logUserAction(
            event: "approval.{$approval->status}",
            description: "Approval #{$approval->id} was {$approval->status} by reviewer #{$approval->reviewed_by}",
            subject: $approval,
        );
    }
}
