<?php

namespace App\Listeners;

use App\Events\ApprovalRequested;
use App\Jobs\SendPlatformNotification;
use App\Models\PlatformNotification;
use App\Notifications\ApprovalRequiredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendApprovalNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    public function handle(ApprovalRequested $event): void
    {
        $approval = $event->approval;

        $approver = $approval->requestedFrom;
        if (! $approver) {
            return;
        }

        // Deduplicate: skip if notification already sent for this approval
        $alreadySent = PlatformNotification::where('user_id', $approver->id)
            ->where('type', 'approval_required')
            ->whereJsonContains('data->approval_id', $approval->id)
            ->exists();

        if ($alreadySent) {
            return;
        }

        dispatch(new SendPlatformNotification(
            userId: $approver->id,
            organizationId: $approval->organization_id,
            type: 'approval_required',
            title: "Needs your answer: {$approval->title}",
            message: $approval->description ?? 'An agent requires your approval to proceed.',
            severity: $approval->risk_level === 'high' ? 'warning' : 'info',
            data: [
                'approval_id' => $approval->id,
                'deployment_id' => $approval->agent_deployment_id,
                'risk_level' => $approval->risk_level,
                'expires_at' => $approval->expires_at?->toIso8601String(),
            ],
            actionUrl: route('governance.approvals', ['approval' => $approval->uuid], false),
            actionLabel: 'Review Now'
        ));

        if ($approval->deployment !== null) {
            $approver->notify(new ApprovalRequiredNotification($approval, $approval->deployment));
        }
    }

    public function failed(ApprovalRequested $event, Throwable $exception): void
    {
        Log::error('[SendApprovalNotification] Failed to send approval notification', [
            'approval_id' => $event->approval->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
