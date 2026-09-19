<?php

namespace App\Services\Social;

use App\Models\AgentDeployment;
use App\Models\SocialConversation;
use App\Models\SocialMessage;
use App\Services\Governance\AuditService;
use App\Services\Governance\DelusionDetectionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Conversation Continuation Engine.
 *
 * Transforms transactional responses into engaging, conversion-oriented
 * conversations that increase engagement, retention, and revenue.
 *
 * Enterprise Rule: AI messages that are outbound to customers MUST be
 * disclosed as AI-generated unless the organization has explicit approval
 * for non-disclosed autonomous engagement.
 */
class ConversationContinuationService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly DelusionDetectionService $delusionDetector,
        private readonly ContinuationContentGenerator $contentGenerator,
    ) {}

    /**
     * Generate an AI response for an inbound message.
     * Respects organizational approval thresholds and governance rules.
     */
    public function generateResponse(
        SocialConversation $conversation,
        SocialMessage $inboundMessage,
        array $intentResult = [],
    ): SocialMessage {
        $deployment = AgentDeployment::withoutGlobalScope('organization')
            ->find($conversation->agent_deployment_id);

        if (! $deployment) {
            throw new \RuntimeException('No agent deployment found for conversation.');
        }

        $requiresApproval = $this->requiresHumanApproval($deployment, $intentResult);
        $shouldDiscloseAi = $this->shouldDiscloseAsAi($deployment);

        try {
            $responseContent = $this->contentGenerator->generate(
                conversation: $conversation,
                inboundMessage: $inboundMessage,
                deployment: $deployment,
                intentResult: $intentResult,
                shouldDiscloseAi: $shouldDiscloseAi,
            );
        } catch (Throwable $e) {
            Log::error('ConversationContinuationService: AI generation failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // ── Delusion detection on AI-generated response ───────────────────────
        $delusionAnalysis = $this->delusionDetector->analyze(
            $inboundMessage->content,
            ['content' => $responseContent['content'], 'confidence' => $responseContent['confidence']],
            ['conversation_id' => $conversation->id, 'intent' => $intentResult]
        );

        // Force human approval if delusion risk is elevated
        if ($delusionAnalysis['risk_score'] >= 60) {
            $requiresApproval = true;
            Log::warning('ConversationContinuationService: elevated delusion risk forces approval', [
                'conversation_id' => $conversation->id,
                'delusion_risk_score' => $delusionAnalysis['risk_score'],
                'flags' => $delusionAnalysis['flags'],
            ]);
        }

        $message = SocialMessage::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $conversation->organization_id,
            'social_conversation_id' => $conversation->id,
            'agent_deployment_id' => $deployment->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'sender_id' => (string) $deployment->id,
            'sender_name' => $deployment->name,
            'content' => $responseContent['content'],
            'message_type' => 'text',
            'status' => $requiresApproval ? 'pending' : 'sent',
            'is_ai_generated' => true,
            'requires_approval' => $requiresApproval,
            'approval_status' => $requiresApproval ? 'pending' : null,
            'was_disclosed_as_ai' => $shouldDiscloseAi,
            'ai_confidence' => $responseContent['confidence'],
            'ai_context' => [
                'intent_level' => $intentResult['intent_level'] ?? null,
                'intent_score' => $intentResult['intent_score'] ?? null,
                'strategy' => $responseContent['strategy'] ?? null,
                'recommended_actions' => $intentResult['recommended_actions'] ?? [],
                'delusion_risk_score' => $delusionAnalysis['risk_score'],
                'delusion_flags' => $delusionAnalysis['flags'],
            ],
            'sent_at' => $requiresApproval ? null : now(),
        ]);

        $this->auditService->logUserAction(
            event: 'social_message.ai_generated',
            description: 'AI-generated social message created',
            data: [
                'requires_approval' => $requiresApproval,
                'was_disclosed_as_ai' => $shouldDiscloseAi,
                'confidence' => $responseContent['confidence'],
                'intent_level' => $intentResult['intent_level'] ?? null,
            ],
            subject: $message,
        );

        return $message;
    }

    private function requiresHumanApproval(AgentDeployment $deployment, array $intentResult): bool
    {
        if ($deployment->requires_human_approval) {
            return true;
        }

        // High-risk intent actions (discounts, demos, transfers) always require approval
        // unless deployment mode is fully autonomous
        if ($deployment->deployment_mode === 'autonomous') {
            return false;
        }

        $highRiskActions = ['offer_discount', 'transfer_to_sales', 'book_demo', 'generate_quote'];
        $recommendedActions = $intentResult['recommended_actions'] ?? [];

        return ! empty(array_intersect($recommendedActions, $highRiskActions));
    }

    private function shouldDiscloseAsAi(AgentDeployment $deployment): bool
    {
        // Enterprise rule: AI must never fully impersonate a human without disclosure
        // Disclosure can only be suppressed if organization explicitly enables non-disclosed mode
        $settings = $deployment->context_config ?? [];

        return ! ($settings['suppress_ai_disclosure'] ?? false);
    }
}
