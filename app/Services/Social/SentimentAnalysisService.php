<?php

namespace App\Services\Social;

use App\DTOs\Social\SentimentAnalysisData;
use App\Models\SocialConversation;
use App\Models\SocialSentimentScore;
use Illuminate\Support\Facades\Log;
use Throwable;

class SentimentAnalysisService
{
    private const SENTIMENT_LABELS = [
        'positive', 'neutral', 'concerned', 'frustrated', 'angry',
    ];

    /**
     * Analyze sentiment of a text using AI and persist the result.
     */
    public function analyze(SentimentAnalysisData $data): SocialSentimentScore
    {
        try {
            $result = $this->callAI($data->text);
        } catch (Throwable $e) {
            Log::warning('SentimentAnalysisService: AI call failed, using fallback', [
                'error' => $e->getMessage(),
            ]);
            $result = $this->fallbackAnalysis($data->text);
        }

        $score = SocialSentimentScore::create([
            'organization_id' => $data->organizationId,
            'social_account_id' => $data->socialAccountId,
            'social_conversation_id' => $data->socialConversationId,
            'agent_deployment_id' => $data->agentDeploymentId,
            'subject_type' => $data->subjectType,
            'platform' => $data->platform,
            'sentiment' => $result['sentiment'],
            'score' => $result['score'],
            'confidence' => $result['confidence'],
            'summary' => $result['summary'] ?? null,
            'detected_topics' => $result['topics'] ?? [],
            'detected_emotions' => $result['emotions'] ?? [],
            'requires_escalation' => in_array($result['sentiment'], ['frustrated', 'angry']),
            'scored_at' => now(),
        ]);

        // Update conversation sentiment if applicable
        if ($data->socialConversationId) {
            SocialConversation::withoutGlobalScope('organization')
                ->where('id', $data->socialConversationId)
                ->update([
                    'sentiment' => $result['sentiment'],
                    'sentiment_score' => $result['score'],
                ]);
        }

        return $score;
    }

    private function callAI(string $text): array
    {
        $raw = app(SocialModelCall::class)->json(<<<'SYSTEM'
You are a precise sentiment analysis engine for customer communications.
Analyze the provided text and respond with a JSON object containing:
{
  "sentiment": one of [positive, neutral, concerned, frustrated, angry],
  "score": a float 0-100 (0=very negative, 50=neutral, 100=very positive),
  "confidence": float 0-100,
  "summary": brief explanation (max 100 chars),
  "topics": array of detected topic strings (e.g. ["pricing", "delivery"]),
  "emotions": array of detected emotion strings
}
SYSTEM, [['role' => 'user', 'text' => "Analyze the sentiment of this customer message:\n\n{$text}"]]);

        return [
            'sentiment' => $this->normalizeSentiment($raw['sentiment'] ?? 'neutral'),
            'score' => (float) ($raw['score'] ?? 50),
            'confidence' => (float) ($raw['confidence'] ?? 80),
            'summary' => $raw['summary'] ?? '',
            'topics' => $raw['topics'] ?? [],
            'emotions' => $raw['emotions'] ?? [],
        ];
    }

    private function fallbackAnalysis(string $text): array
    {
        $lower = strtolower($text);
        $negativeWords = ['angry', 'terrible', 'awful', 'hate', 'worst', 'horrible', 'cancel', 'refund', 'scam'];
        $positiveWords = ['great', 'love', 'amazing', 'excellent', 'perfect', 'best', 'thank', 'happy'];

        $negCount = count(array_filter($negativeWords, fn ($w) => str_contains($lower, $w)));
        $posCount = count(array_filter($positiveWords, fn ($w) => str_contains($lower, $w)));

        if ($negCount > $posCount + 1) {
            $sentiment = $negCount > 2 ? 'angry' : 'frustrated';
            $score = 20.0;
        } elseif ($posCount > $negCount) {
            $sentiment = 'positive';
            $score = 80.0;
        } else {
            $sentiment = 'neutral';
            $score = 50.0;
        }

        return [
            'sentiment' => $sentiment,
            'score' => $score,
            'confidence' => 60.0,
            'summary' => 'Keyword-based fallback analysis',
            'topics' => [],
            'emotions' => [],
        ];
    }

    private function normalizeSentiment(string $sentiment): string
    {
        $normalized = strtolower(trim($sentiment));

        return in_array($normalized, self::SENTIMENT_LABELS, true) ? $normalized : 'neutral';
    }
}
