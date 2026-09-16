<?php

namespace App\Actions\Social;

use App\DTOs\Social\SocialMessageResponseData;
use App\Events\SocialMessageReceived;
use App\Jobs\GenerateSocialResponseJob;
use App\Models\SocialConversation;
use App\Models\SocialMessage;
use App\Services\Governance\AuditService;
use App\Services\Social\LeadQualificationService;
use App\Services\Social\SentimentAnalysisService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RespondToSocialMessageAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly SentimentAnalysisService $sentimentService,
        private readonly LeadQualificationService $leadService,
    ) {}

    /**
     * Send a human-crafted or pre-approved outbound message to a conversation.
     */
    public function execute(SocialMessageResponseData $data, int $actorId): SocialMessage
    {
        $conversation = SocialConversation::findOrFail($data->socialConversationId);

        Gate::authorize('update', $conversation);

        $message = SocialMessage::create($data->toArray());

        // Update first-response time if this is the first reply
        if (! $conversation->first_response_at) {
            $responseTimeSecs = now()->diffInSeconds($conversation->created_at);
            $conversation->update([
                'first_response_at' => now(),
                'response_time_seconds' => $responseTimeSecs,
                'status' => 'in_progress',
            ]);
        }

        $this->auditService->logUserAction(
            event: 'social_message.sent',
            description: 'Outbound social message sent',
            data: [
                'is_ai_generated' => $data->isAiGenerated,
                'was_disclosed_as_ai' => $data->wasDisclosedAsAi,
                'conversation_id' => $data->socialConversationId,
            ],
            subject: $message,
        );

        return $message;
    }

    /**
     * Receive an inbound message from a customer and trigger async AI processing.
     *
     * SECURITY: Every inbound message is scanned for prompt injection before
     * dispatching to the AI response pipeline. Injections are logged as security
     * events and the AI job is NOT dispatched for flagged messages.
     */
    public function receiveInbound(
        int $organizationId,
        int $socialConversationId,
        string $content,
        string $senderPlatformId,
        string $senderName,
        string $messageType = 'text',
        array $mediaAttachments = [],
        ?int $agentDeploymentId = null,
    ): SocialMessage {
        // ── SECURITY: Prompt injection scan on all inbound user content ───────
        $isInjectionAttempt = $this->auditService->detectPromptInjection($content);

        if ($isInjectionAttempt) {
            Log::warning('[SCCS Security] Prompt injection detected in inbound social message', [
                'organization_id' => $organizationId,
                'conversation_id' => $socialConversationId,
                'sender_id' => $senderPlatformId,
                'content_hash' => hash('sha256', $content),
            ]);

            $this->auditService->logSecurityEvent(
                organizationId: $organizationId,
                eventType: 'prompt_injection',
                severity: 'warning',
                title: 'Prompt Injection Attempt via Social Message',
                description: "Inbound social message from {$senderName} contained prompt injection patterns.",
                data: [
                    'conversation_id' => $socialConversationId,
                    'sender_id' => $senderPlatformId,
                    'content_hash' => hash('sha256', $content),
                ],
            );
        }

        $message = SocialMessage::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'social_conversation_id' => $socialConversationId,
            'agent_deployment_id' => $agentDeploymentId,
            'direction' => 'inbound',
            'sender_type' => 'contact',
            'sender_id' => $senderPlatformId,
            'sender_name' => $senderName,
            'content' => $content,
            'media_attachments' => $mediaAttachments,
            'message_type' => $messageType,
            'status' => 'delivered',
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        event(new SocialMessageReceived($message));

        // Do NOT dispatch to AI if injection attempt detected — log only
        if ($agentDeploymentId && ! $isInjectionAttempt) {
            GenerateSocialResponseJob::dispatch($message);
        }

        return $message;
    }
}
