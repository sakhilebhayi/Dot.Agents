<?php

namespace App\Listeners;

use App\Events\NegativeSentimentDetected;
use App\Jobs\SendPlatformNotification;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyOnNegativeSentiment implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function handle(NegativeSentimentDetected $event): void
    {
        $score = $event->sentimentScore;

        // agent_deployment_id is nullable (e.g. human-only conversations) —
        // only audit-log against a deployment when one is actually attached.
        if ($score->agentDeployment) {
            $this->auditService->logAgentAction(
                $score->agentDeployment,
                'social.negative_sentiment',
                [
                    'sentiment_score_id' => $score->id,
                    'sentiment_score' => $score->score,
                    'platform' => $score->platform,
                    'content_snippet' => $score->summary,
                ]
            );
        }

        // Alert org admins when sentiment drops critically
        if ($score->score <= -0.7) {
            SendPlatformNotification::toAdmins(
                organizationId: $score->organization_id,
                type: 'negative_sentiment_alert',
                title: 'Critical Negative Sentiment Detected',
                message: "Sentiment score of {$score->score} detected on {$score->platform}. Immediate review recommended.",
                severity: 'error',
                data: ['sentiment_score_id' => $score->id, 'score' => $score->score],
                actionUrl: '/social/sentiment'
            );
        }
    }

    public function failed(NegativeSentimentDetected $event, Throwable $exception): void
    {
        Log::warning('[NotifyOnNegativeSentiment] Failed', [
            'sentiment_score_id' => $event->sentimentScore->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
