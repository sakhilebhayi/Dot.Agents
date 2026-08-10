<?php

namespace App\Jobs;

use App\Models\AgentDeployment;
use App\Models\AgentMessage;
use App\Models\AgentSession;
use App\Services\AI\AgentOrchestrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs AgentOrchestrationService::processMessage() on the reserved 'ai'
 * queue instead of inline in the Livewire request/response cycle -- an AI
 * completion call (now real, via Prism -- see AgentModelCaller) can take
 * several seconds, which is too long to hold open an HTTP/Livewire request.
 *
 * AgentChat polls for the resulting assistant AgentMessage rather than
 * waiting on this job directly; see AgentChat::pollForReply().
 */
class ProcessAgentMessage implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 15;

    public int $timeout = 120;

    public function __construct(
        public readonly AgentDeployment $deployment,
        public readonly AgentSession $session,
        public readonly string $userMessage,
    ) {
        $this->onQueue('ai');
    }

    public function handle(AgentOrchestrationService $orchestrator): void
    {
        $orchestrator->processMessage($this->deployment, $this->session, $this->userMessage);
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('ProcessAgentMessage: all retries exhausted', [
            'deployment_id' => $this->deployment->id,
            'session_id' => $this->session->id,
            'error' => $exception->getMessage(),
        ]);

        // Without this, a failed job leaves the chat UI's "typing..."
        // indicator spinning forever with nothing ever arriving to clear
        // it (AgentChat::pollForReply() waits for a new assistant message).
        AgentMessage::create([
            'session_id' => $this->session->id,
            'role' => 'assistant',
            'content' => "I'm having trouble responding right now. Please try again shortly.",
            'metadata' => ['finish_reason' => 'error'],
        ]);
    }
}
