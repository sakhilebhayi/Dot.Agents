<?php

namespace App\Listeners;

use App\Events\SocialLeadCaptured;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogSocialLeadCaptured implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function handle(SocialLeadCaptured $event): void
    {
        $lead = $event->lead;

        // agent_deployment_id is nullable (e.g. human-only conversations) —
        // only audit-log against a deployment when one is actually attached.
        if (! $lead->agentDeployment) {
            return;
        }

        $this->auditService->logAgentAction(
            $lead->agentDeployment,
            'social.lead_captured',
            [
                'lead_id' => $lead->id,
                'platform' => $lead->platform,
                'lead_score' => $lead->lead_score,
                'contact_name' => $lead->contact_name,
            ]
        );
    }

    public function failed(SocialLeadCaptured $event, Throwable $exception): void
    {
        Log::warning('[LogSocialLeadCaptured] Failed', [
            'lead_id' => $event->lead->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
