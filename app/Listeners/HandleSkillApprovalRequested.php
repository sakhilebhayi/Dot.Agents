<?php

namespace App\Listeners;

use App\Events\SkillApprovalRequested;
use App\Jobs\SendPlatformNotification;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles the full governance lifecycle when a skill requires human approval
 * before execution: creates an audit record, notifies the assigned approver,
 * and escalates to org admins if no approver is assigned.
 */
class HandleSkillApprovalRequested implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function handle(SkillApprovalRequested $event): void
    {
        $approval = $event->approval;
        $deployment = $approval->deployment;

        // Audit: a skill has been blocked pending human approval
        if ($deployment) {
            $this->auditService->logAgentAction(
                $deployment,
                'skill.approval.requested',
                [
                    'skill_approval_id' => $approval->id,
                    'skill_id' => $approval->skill_id,
                    'skill_name' => $approval->skill?->name,
                    'requested_by_agent' => $deployment?->display_name,
                    'risk_level' => $approval->risk_level ?? 'medium',
                ]
            );
        }

        // Notify all org admins — AgentSkillApproval has no designated single approver,
        // so the entire admin group must action it from the governance queue.
        $severity = ($approval->risk_level === 'high') ? 'warning' : 'info';

        SendPlatformNotification::toAdmins(
            organizationId: $approval->organization_id,
            type: 'skill_approval_required',
            title: "Skill Approval Required: {$approval->skill?->name}",
            message: "Agent '{$deployment?->display_name}' is requesting permission to use the '{$approval->skill?->name}' skill. Risk level: ".($approval->risk_level ?? 'medium').'.',
            severity: $severity,
            data: [
                'skill_approval_id' => $approval->id,
                'deployment_id' => $deployment?->id,
                'risk_level' => $approval->risk_level ?? 'medium',
            ],
            actionUrl: '/governance/approvals'
        );
    }

    public function failed(SkillApprovalRequested $event, Throwable $exception): void
    {
        Log::error('[HandleSkillApprovalRequested] Failed', [
            'skill_approval_id' => $event->approval->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
