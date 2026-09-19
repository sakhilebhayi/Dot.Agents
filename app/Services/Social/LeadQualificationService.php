<?php

namespace App\Services\Social;

use App\Models\SocialConversation;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class LeadQualificationService
{
    /**
     * Score the purchase intent from a conversation context.
     *
     * @return array{intent_level: string, intent_score: float, lead_score: float, recommended_actions: array}
     */
    public function scoreIntent(SocialConversation $conversation, string $latestMessage): array
    {
        try {
            return $this->aiScoreIntent($conversation, $latestMessage);
        } catch (Throwable $e) {
            Log::warning('LeadQualificationService: AI scoring failed', ['error' => $e->getMessage()]);

            return $this->keywordScoreIntent($latestMessage);
        }
    }

    private function aiScoreIntent(SocialConversation $conversation, string $latestMessage): array
    {
        $history = $conversation->messages()
            ->where('direction', 'inbound')
            ->latest()
            ->limit(5)
            ->pluck('content')
            ->implode("\n---\n");

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => <<<'SYSTEM'
You are a sales intent scoring engine. Analyze customer messages for purchase intent.

Respond with JSON:
{
  "intent_score": float 0-100,
  "intent_level": one of [browsing, interested, considering, ready_to_buy, high_intent],
  "lead_score": float 0-100,
  "reasoning": string,
  "recommended_actions": array of strings from [offer_discount, book_demo, transfer_to_sales, send_product_info, schedule_follow_up, generate_crm_record, send_case_study]
}

Scoring guide:
- 0-24: browsing (general curiosity)
- 25-49: interested (asking product questions)
- 50-74: considering (comparing, evaluating)
- 75-89: ready_to_buy (asking pricing, availability, next steps)
- 90-100: high_intent (ready now, requesting purchase/demo/quote)
SYSTEM,
                ],
                [
                    'role' => 'user',
                    'content' => "Conversation history (last 5 messages):\n{$history}\n\nLatest message:\n{$latestMessage}",
                ],
            ],
        ]);

        $raw = json_decode($response->choices[0]->message->content, true);

        return [
            'intent_score' => (float) ($raw['intent_score'] ?? 0),
            'intent_level' => $raw['intent_level'] ?? 'browsing',
            'lead_score' => (float) ($raw['lead_score'] ?? 0),
            'recommended_actions' => $raw['recommended_actions'] ?? [],
        ];
    }

    private function keywordScoreIntent(string $message): array
    {
        $lower = strtolower($message);

        $highIntentWords = ['buy now', 'purchase', 'order', 'sign up', 'sign me up', 'ready to buy'];
        $readyWords = ['how much', 'price', 'pricing', 'cost', 'quote', 'demo', 'trial'];
        $consideringWords = ['compare', 'versus', 'vs', 'better than', 'features', 'include'];
        $interestedWords = ['tell me more', 'more info', 'interested', 'learn more', 'what is'];

        foreach ($highIntentWords as $w) {
            if (str_contains($lower, $w)) {
                return ['intent_score' => 92, 'intent_level' => 'high_intent', 'lead_score' => 85, 'recommended_actions' => ['transfer_to_sales', 'book_demo']];
            }
        }
        foreach ($readyWords as $w) {
            if (str_contains($lower, $w)) {
                return ['intent_score' => 80, 'intent_level' => 'ready_to_buy', 'lead_score' => 70, 'recommended_actions' => ['offer_discount', 'book_demo']];
            }
        }
        foreach ($consideringWords as $w) {
            if (str_contains($lower, $w)) {
                return ['intent_score' => 60, 'intent_level' => 'considering', 'lead_score' => 50, 'recommended_actions' => ['send_case_study']];
            }
        }
        foreach ($interestedWords as $w) {
            if (str_contains($lower, $w)) {
                return ['intent_score' => 35, 'intent_level' => 'interested', 'lead_score' => 30, 'recommended_actions' => ['send_product_info']];
            }
        }

        return ['intent_score' => 10, 'intent_level' => 'browsing', 'lead_score' => 10, 'recommended_actions' => []];
    }
}
