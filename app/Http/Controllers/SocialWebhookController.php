<?php

namespace App\Http\Controllers;

use App\Actions\Social\RespondToSocialMessageAction;
use App\Models\SocialAccount;
use App\Models\SocialConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SocialWebhookController extends Controller
{
    public function verify(Request $request, string $platform): Response
    {
        if ($platform !== 'facebook') {
            return response('Unsupported platform', 403);
        }

        // PHP's parse_str() rewrites dots in query-string keys to underscores,
        // so Meta's hub.mode/hub.verify_token/hub.challenge arrive as these keys.
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expectedToken = config('services.facebook.webhook_verify_token');

        if ($mode === 'subscribe' && $expectedToken && hash_equals($expectedToken, (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('SocialWebhookController: Facebook webhook verification failed', [
            'mode' => $mode,
        ]);

        return response('Verification failed', 403);
    }

    public function receive(Request $request, string $platform): Response
    {
        if ($platform !== 'facebook') {
            Log::info('SocialWebhookController: inbound ingestion not implemented for platform', [
                'platform' => $platform,
            ]);

            return response('EVENT_RECEIVED', 200);
        }

        if (! $this->hasValidFacebookSignature($request)) {
            Log::warning('SocialWebhookController: Facebook webhook signature verification failed');

            return response('Invalid signature', 403);
        }

        $payload = $request->json()->all();

        foreach ($payload['entry'] ?? [] as $entry) {
            $pageId = $entry['id'] ?? null;

            foreach ($entry['messaging'] ?? [] as $messagingEvent) {
                $this->processFacebookMessagingEvent($messagingEvent, $pageId);
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function hasValidFacebookSignature(Request $request): bool
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

    private function processFacebookMessagingEvent(array $messagingEvent, ?string $pageId): void
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

        app(RespondToSocialMessageAction::class)->receiveInbound(
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
