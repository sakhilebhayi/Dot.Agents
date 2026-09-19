<?php

namespace App\Actions\Social;

use App\Models\SocialAccount;
use App\Models\SocialConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Owns Facebook Messenger webhook signature verification and inbound payload
 * ingestion, extracted from SocialWebhookController to keep the controller
 * as a thin HTTP layer.
 */
class ReceiveFacebookWebhookAction
{
    public function __construct(
        private readonly RespondToSocialMessageAction $respondToSocialMessageAction,
    ) {}

    public function hasValidSignature(Request $request): bool
    {
        $appSecret = config('services.facebook.client_secret');
        $header = (string) $request->header('X-Hub-Signature-256', '');

        if (! $appSecret || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $providedSignature = substr($header, strlen('sha256='));
        $expectedSignature = hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, $providedSignature);
    }

    public function handle(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            $pageId = $entry['id'] ?? null;

            foreach ($entry['messaging'] ?? [] as $messagingEvent) {
                $this->processMessagingEvent($messagingEvent, $pageId);
            }
        }
    }

    private function processMessagingEvent(array $messagingEvent, ?string $pageId): void
    {
        $messageText = $messagingEvent['message']['text'] ?? null;
        $senderPlatformId = $messagingEvent['sender']['id'] ?? null;

        if (! $messageText || ! $senderPlatformId || ! $pageId) {
            return;
        }

        $socialAccount = SocialAccount::withoutGlobalScope('organization')
            ->where('platform', 'facebook')
            ->where('platform_account_id', $pageId)
            ->first();

        if (! $socialAccount) {
            Log::warning('SocialWebhookController: no SocialAccount found for Facebook Page', [
                'page_id' => $pageId,
            ]);

            return;
        }

        $conversation = SocialConversation::withoutGlobalScope('organization')
            ->where('social_account_id', $socialAccount->id)
            ->where('contact_platform_id', $senderPlatformId)
            ->where('platform', 'facebook')
            ->first();

        if (! $conversation) {
            $conversation = SocialConversation::withoutGlobalScope('organization')->create([
                'organization_id' => $socialAccount->organization_id,
                'social_account_id' => $socialAccount->id,
                'agent_deployment_id' => $socialAccount->agent_deployment_id,
                'platform' => 'facebook',
                'channel_type' => 'messenger',
                'contact_platform_id' => $senderPlatformId,
                'contact_name' => 'Facebook User',
                'status' => 'open',
            ]);
        }

        $attachments = $messagingEvent['message']['attachments'] ?? [];

        $this->respondToSocialMessageAction->receiveInbound(
            organizationId: $socialAccount->organization_id,
            socialConversationId: $conversation->id,
            content: $messageText,
            senderPlatformId: $senderPlatformId,
            senderName: 'Facebook User',
            messageType: $attachments ? 'image' : 'text',
            mediaAttachments: $attachments,
            agentDeploymentId: $socialAccount->agent_deployment_id,
        );
    }
}
