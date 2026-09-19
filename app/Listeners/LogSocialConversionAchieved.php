<?php

namespace App\Listeners;

use App\Events\SocialConversionAchieved;
use App\Jobs\SendPlatformNotification;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogSocialConversionAchieved implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function handle(SocialConversionAchieved $event): void
    {
        $conversion = $event->conversion;

        // agent_deployment_id is nullable (e.g. human-only conversations) —
        // only audit-log against a deployment when one is actually attached.
        if ($conversion->agentDeployment) {
            $this->auditService->logAgentAction(
                $conversion->agentDeployment,
                'social.conversion_achieved',
                [
                    'conversion_id' => $conversion->id,
                    'conversion_type' => $conversion->conversion_type,
                    'platform' => $conversion->platform,
                    'value' => $conversion->revenue,
                ]
            );
        }

        SendPlatformNotification::toAdmins(
            organizationId: $conversion->organization_id,
            type: 'social_conversion',
            title: 'Social Conversion Achieved',
            message: "A {$conversion->conversion_type} conversion was recorded on {$conversion->platform}.",
            severity: 'success',
            data: ['conversion_id' => $conversion->id, 'value' => $conversion->revenue],
            actionUrl: "/social/conversions/{$conversion->id}"
        );
    }

    public function failed(SocialConversionAchieved $event, Throwable $exception): void
    {
        Log::warning('[LogSocialConversionAchieved] Failed', [
            'conversion_id' => $event->conversion->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
