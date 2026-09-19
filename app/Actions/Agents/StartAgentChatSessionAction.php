<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\StartAgentChatSessionData;
use App\Events\AgentChatStarted;
use App\Models\AgentDeployment;
use App\Models\AgentMessage;
use App\Models\AgentSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StartAgentChatSessionAction
{
    /**
     * Create a new interactive chat session for the given deployment.
     *
     * Persists the AgentSession, optionally seeds a system-prompt message,
     * and fires AgentChatStarted, which the LogAgentChatStarted listener
     * uses to record the audit trail entry via AuditService.
     *
     * @param  AgentDeployment  $deployment  The deployment hosting the chat session.
     * @param  StartAgentChatSessionData  $data  DTO carrying user ID, title, and org context.
     * @return AgentSession The newly created chat session.
     *
     * @throws AuthorizationException When actor lacks 'chat' permission.
     */
    public function execute(AgentDeployment $deployment, StartAgentChatSessionData $data): AgentSession
    {
        Gate::authorize('chat', $deployment);

        $session = AgentSession::create([
            'uuid' => (string) Str::uuid(),
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $data->organizationId ?? $deployment->organization_id,
            'user_id' => $data->userId,
            'session_type' => 'conversation',
            'title' => $data->title ?? 'New Conversation',
            'status' => 'active',
            'started_at' => now(),
        ]);

        event(new AgentChatStarted($session));

        return $session;
    }

    /**
     * Store a user message in the session.
     */
    public function storeUserMessage(AgentSession $session, string $content): AgentMessage
    {
        $message = AgentMessage::create([
            'uuid' => (string) Str::uuid(),
            'session_id' => $session->id,
            'organization_id' => $session->organization_id,
            'role' => 'user',
            'content' => $content,
        ]);

        $session->increment('message_count');

        return $message;
    }

    /**
     * Close a session and mark it as completed.
     */
    public function endSession(AgentSession $session): void
    {
        $session->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);
    }
}
