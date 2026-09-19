<?php

namespace App\Services\Social;

use App\Models\AgentDeployment;
use App\Models\SocialConversation;
use App\Models\SocialMessage;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Continuation Content Generator.
 *
 * Builds the AI prompt from conversation history and deployment context,
 * invokes the model, and parses the structured response into content,
 * strategy, and confidence.
 *
 * Extracted from ConversationContinuationService to keep prompt
 * construction and model invocation separate from response
 * orchestration and governance decisions.
 */
class ContinuationContentGenerator
{
    /**
     * Generate the AI content for a conversation continuation.
     *
     * @return array{content: string, strategy: string, confidence: float}
     */
    public function generate(
        SocialConversation $conversation,
        SocialMessage $inboundMessage,
        AgentDeployment $deployment,
        array $intentResult,
        bool $shouldDiscloseAi,
    ): array {
        $recentMessages = $conversation->messages()
            ->latest()
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn (SocialMessage $m) => [
                'role' => $m->direction === 'inbound' ? 'user' : 'assistant',
                'content' => $m->content,
            ])
            ->values()
            ->toArray();

        $intentContext = '';
        if (! empty($intentResult)) {
            $intentContext = "\n\nIntent context: Customer shows {$intentResult['intent_level']} intent (score: {$intentResult['intent_score']}).";
            if (! empty($intentResult['recommended_actions'])) {
                $actions = implode(', ', $intentResult['recommended_actions']);
                $intentContext .= " Recommended actions: {$actions}.";
            }
        }

        $disclosureInstruction = $shouldDiscloseAi
            ? 'If appropriate, naturally disclose you are an AI assistant (e.g., "As your AI assistant..." or "I\'m an AI here to help...").'
            : '';

        $systemPrompt = <<<SYSTEM
You are {$deployment->name}, a Customer Success Agent for an enterprise organization.
Your goal is to engage naturally, answer questions helpfully, and guide the customer toward a positive outcome.

Conversation continuation principles:
1. Never give one-word or transactional responses — always continue the conversation naturally.
2. Ask a relevant follow-up question to increase engagement.
3. If intent is high, subtly guide toward next steps (demo, quote, purchase).
4. Be empathetic, professional, and concise.
5. Never promise refunds, discounts beyond authority, or make legal commitments.
6. Never share confidential organizational data.
7. If the customer is frustrated or angry, acknowledge their frustration first before resolving.
{$disclosureInstruction}
{$deployment->custom_instructions}
{$intentContext}

Respond with JSON: {"content": "your message here", "strategy": "engagement_strategy_used", "confidence": 0-100}
SYSTEM;

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $recentMessages,
        );

        $response = OpenAI::chat()->create([
            'model' => $deployment->model_override ?? 'gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 500,
            'response_format' => ['type' => 'json_object'],
            'messages' => $messages,
        ]);

        $raw = json_decode($response->choices[0]->message->content, true);

        return [
            'content' => $raw['content'] ?? 'Thank you for your message. How can I assist you further?',
            'strategy' => $raw['strategy'] ?? 'general_engagement',
            'confidence' => (float) ($raw['confidence'] ?? 80),
        ];
    }
}
