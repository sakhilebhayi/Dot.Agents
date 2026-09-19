<?php

namespace App\Listeners;

use App\Events\PurchaseIntentDetected;
use App\Jobs\SendPlatformNotification;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogPurchaseIntentDetected implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function handle(PurchaseIntentDetected $event): void
    {
        $conversation = $event->conversation;

        // agent_deployment_id is nullable (e.g. human-only conversations) —
        // only audit-log against a deployment when one is actually attached.
        if ($conversation->agentDeployment) {
            $this->auditService->logAgentAction(
                $conversation->agentDeployment,
                'social.purchase_intent_detected',
                [
                    'conversation_id' => $conversation->id,
                    'intent_score' => $event->intentScore,
                    'intent_level' => $event->intentLevel,
                    'platform' => $conversation->platform,
                ]
            );
        }

        if ($event->intentLevel === 'high') {
            SendPlatformNotification::toAdmins(
                organizationId: $conversation->organization_id,
                type: 'high_purchase_intent',
                title: 'High Purchase Intent Detected',
                message: "A conversation on {$conversation->platform} shows high purchase intent (score: {$event->intentScore}).",
                severity: 'info',
                data: [
                    'conversation_id' => $conversation->id,
                    'intent_score' => $event->intentScore,
                ],
                actionUrl: "/social/conversations/{$conversation->id}"
            );
        }
    }

    public function failed(PurchaseIntentDetected $event, Throwable $exception): void
    {
        Log::warning('[LogPurchaseIntentDetected] Failed', [
            'conversation_id' => $event->conversation->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
