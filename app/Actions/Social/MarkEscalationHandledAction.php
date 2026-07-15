<?php

declare(strict_types=1);

namespace App\Actions\Social;

use App\DTOs\Social\MarkEscalationHandledData;
use App\Events\EscalationHandled;
use App\Models\SocialSentimentScore;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class MarkEscalationHandledAction
{
    public function __construct(private readonly AuditService $auditService) {}

    public function execute(MarkEscalationHandledData $data): void
    {
        $score = SocialSentimentScore::where('organization_id', $data->organizationId)
            ->findOrFail($data->scoreId);

        Gate::authorize('update', $score);

        $score->update(['escalation_handled' => true]);

        $this->auditService->logUserAction(
            event: 'social_escalation.handled',
            description: "Sentiment escalation #{$data->scoreId} marked as handled",
            subject: $score,
        );

        event(new EscalationHandled($score));
    }
}
