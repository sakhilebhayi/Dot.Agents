<?php

namespace App\Listeners;

use App\Events\SocialMessageReceived;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogSocialMessageReceived implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function handle(SocialMessageReceived $event): void
    {
        $message = $event->message;
        $deployment = $message->agentDeployment;

        if (! $deployment) {
            return;
        }

        $this->auditService->logAgentAction(
            $deployment,
            'social.message_received',
            [
                'message_id' => $message->id,
                'platform' => $message->socialConversation->platform,
                'direction' => $message->direction,
            ]
        );
    }

    public function failed(SocialMessageReceived $event, Throwable $exception): void
    {
        Log::warning('[LogSocialMessageReceived] Failed', [
            'message_id' => $event->message->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
