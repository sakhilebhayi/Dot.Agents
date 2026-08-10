<?php

namespace App\Livewire\Agents;

use App\Actions\Agents\StartAgentChatSessionAction;
use App\DTOs\Agents\StartAgentChatSessionData;
use App\Jobs\ProcessAgentMessage;
use App\Models\AgentDeployment;
use App\Models\AgentMessage;
use App\Models\AgentSession;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AgentChat extends Component
{
    public int $deploymentId;

    public ?int $sessionId = null;

    public string $message = '';

    public bool $isTyping = false;

    public bool $showTaskPanel = false;

    /**
     * The id of the AgentMessage most recently sent to the queue -- while
     * waiting, pollForReply() watches for any assistant message created
     * after this row.
     */
    public ?int $pendingMessageId = null;

    public function mount(int $deploymentId): void
    {
        $this->deploymentId = $deploymentId;
        $this->initSession();
    }

    #[Computed]
    public function deployment(): AgentDeployment
    {
        return AgentDeployment::with('agent')->findOrFail($this->deploymentId);
    }

    #[Computed]
    public function session(): ?AgentSession
    {
        return $this->sessionId ? AgentSession::find($this->sessionId) : null;
    }

    #[Computed]
    public function chatMessages()
    {
        if (! $this->sessionId) {
            return collect();
        }

        return AgentMessage::where('session_id', $this->sessionId)
            ->orderBy('created_at')
            ->get();
    }

    public function sendMessage(): void
    {
        $this->validate(['message' => 'required|string|max:10000']);

        // Rate-limit: 20 messages/min per user, 200/min per org
        $rateLimitKey = 'chat-user:'.(Auth::id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts('agent-chat:'.$rateLimitKey, 20)) {
            $this->dispatch('security-alert', message: 'Too many messages. Please wait before sending again.');

            return;
        }
        RateLimiter::hit('agent-chat:'.$rateLimitKey, 60);

        $deployment = $this->deployment;
        $session = $this->session;
        $userMessage = $this->message;
        $this->message = '';

        // Security check: prompt injection detection
        $auditService = app(AuditService::class);
        if ($auditService->detectPromptInjection($userMessage, $deployment)) {
            $this->dispatch('security-alert', message: 'Suspicious input detected and logged.');

            return;
        }

        // Store user message via Action
        $chatAction = app(StartAgentChatSessionAction::class);
        $storedUserMessage = $chatAction->storeUserMessage($session, $userMessage);

        // Process through orchestration service on the 'ai' queue rather
        // than inline -- a real model completion can take several seconds,
        // too long to hold open this request. pollForReply() picks up the
        // assistant's reply once the job finishes.
        $this->pendingMessageId = $storedUserMessage->id;
        $this->isTyping = true;

        ProcessAgentMessage::dispatch($deployment, $session, $userMessage);

        // Refresh messages
        unset($this->chatMessages);
    }

    /**
     * Polled from the chat view (see resources/views/livewire/agents/agent-chat.blade.php)
     * while isTyping is true. A no-op once the reply has arrived or there's
     * nothing pending, so it's cheap to leave the poll running.
     */
    public function pollForReply(): void
    {
        if (! $this->isTyping || ! $this->pendingMessageId || ! $this->sessionId) {
            return;
        }

        $hasReply = AgentMessage::where('session_id', $this->sessionId)
            ->where('id', '>', $this->pendingMessageId)
            ->where('role', 'assistant')
            ->exists();

        if ($hasReply) {
            $this->isTyping = false;
            $this->pendingMessageId = null;
            unset($this->chatMessages);
        }
    }

    public function newSession(): void
    {
        $this->sessionId = null;
        $this->initSession();
        unset($this->messages, $this->session);
    }

    private function initSession(): void
    {
        $deployment = AgentDeployment::findOrFail($this->deploymentId);
        $session = app(StartAgentChatSessionAction::class)->execute(
            $deployment,
            new StartAgentChatSessionData(
                userId: Auth::id(),
                agentDeploymentId: $deployment->id,
                organizationId: session('current_organization_id'),
            ),
        );

        $this->sessionId = $session->id;
    }

    public function endSession(): void
    {
        if ($this->sessionId) {
            $session = AgentSession::find($this->sessionId);
            if ($session) {
                app(StartAgentChatSessionAction::class)->endSession($session);
            }
        }

        $this->redirect(route('agents.deployments'));
    }

    public function render()
    {
        return view('livewire.agents.agent-chat');
    }
}
