<?php

namespace App\Actions\Social;

use App\Events\ConversationEscalated;
use App\Models\SocialConversation;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class EscalateConversationAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function execute(
        SocialConversation $conversation,
        int $escalatedTo,
        int $escalatedBy,
        string $reason = '',
    ): SocialConversation {
        Gate::authorize('update', $conversation);

        $conversation->update([
            'status' => 'escalated',
            'is_escalated' => true,
            'requires_human' => true,
            'escalated_to' => $escalatedTo,
            'escalated_at' => now(),
            'assigned_user_id' => $escalatedTo,
            'priority' => 'urgent',
        ]);

        $this->auditService->logUserAction(
            event: 'social_conversation.escalated',
            description: "Conversation escalated: {$reason}",
            data: [
                'escalated_to' => $escalatedTo,
                'reason' => $reason,
                'sentiment' => $conversation->sentiment,
                'platform' => $conversation->platform,
            ],
            subject: $conversation,
        );

        event(new ConversationEscalated($conversation->fresh(), $escalatedTo, $reason));

        return $conversation->fresh();
    }
}
