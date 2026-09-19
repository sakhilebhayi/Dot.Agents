<?php

namespace App\Listeners;

use App\Events\AgentChatStarted;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogAgentChatStarted implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function handle(AgentChatStarted $event): void
    {
        $session = $event->session;
        $deployment = $session->deployment;

        if (! $deployment) {
            return;
        }

        $this->auditService->logAgentAction($deployment, 'agent_chat_started', [
            'session_id' => $session->id,
            'user_id' => $session->user_id,
            'organization_id' => $session->organization_id,
            'session_type' => $session->session_type,
            'started_at' => $session->started_at->toIso8601String(),
        ]);
    }

    public function failed(AgentChatStarted $event, Throwable $exception): void
    {
        Log::error('[LogAgentChatStarted] Failed to log chat session start', [
            'session_id' => $event->session->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
