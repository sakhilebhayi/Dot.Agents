<?php

namespace App\Http\Controllers;

use App\Actions\Social\ReceiveFacebookWebhookAction;
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

    public function receive(Request $request, string $platform, ReceiveFacebookWebhookAction $action): Response
    {
        if ($platform !== 'facebook') {
            Log::info('SocialWebhookController: inbound ingestion not implemented for platform', [
                'platform' => $platform,
            ]);

            return response('EVENT_RECEIVED', 200);
        }

        if (! $action->hasValidSignature($request)) {
            Log::warning('SocialWebhookController: Facebook webhook signature verification failed');

            return response('Invalid signature', 403);
        }

        $action->handle($request->json()->all());

        return response('EVENT_RECEIVED', 200);
    }
}
