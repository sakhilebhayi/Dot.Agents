<?php

namespace App\Listeners;

use App\Events\ConversationEscalated;
use App\Events\EscalationHandled;
use App\Events\LeadQualified;
use App\Events\SocialAccountConnected;
use App\Events\SocialAccountDisconnected;
use App\Events\SocialPostApproved;
use App\Events\SocialPostScheduled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogSocialLifecycleEvents implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    public function handleSocialAccountConnected(SocialAccountConnected $event): void
    {
        Log::info('[Social] Account connected', [
            'organization_id' => $event->account->organization_id,
            'platform' => $event->account->platform,
            'account_name' => $event->account->account_name,
        ]);
    }

    public function handleSocialAccountDisconnected(SocialAccountDisconnected $event): void
    {
        Log::info('[Social] Account disconnected', [
            'organization_id' => $event->organizationId,
            'platform' => $event->platform,
            'account_name' => $event->accountName,
        ]);
    }

    public function handleSocialPostApproved(SocialPostApproved $event): void
    {
        Log::info('[Social] Post approved', [
            'post_id' => $event->post->id,
            'organization_id' => $event->post->organization_id,
            'approver_id' => $event->approverId,
        ]);
    }

    public function handleSocialPostScheduled(SocialPostScheduled $event): void
    {
        Log::info('[Social] Post scheduled', [
            'post_id' => $event->post->id,
            'organization_id' => $event->post->organization_id,
            'scheduled_at' => $event->post->scheduled_at,
        ]);
    }

    public function handleConversationEscalated(ConversationEscalated $event): void
    {
        Log::info('[Social] Conversation escalated', [
            'conversation_id' => $event->conversation->id,
            'organization_id' => $event->conversation->organization_id,
            'escalated_to' => $event->escalatedTo,
            'reason' => $event->reason,
        ]);
    }

    public function handleEscalationHandled(EscalationHandled $event): void
    {
        Log::info('[Social] Escalation handled', [
            'score_id' => $event->score->id,
            'organization_id' => $event->score->organization_id,
        ]);
    }

    public function handleLeadQualified(LeadQualified $event): void
    {
        Log::info('[Social] Lead qualified', [
            'lead_id' => $event->lead->id,
            'organization_id' => $event->lead->organization_id,
            'intent_level' => $event->intentLevel,
        ]);
    }
}
