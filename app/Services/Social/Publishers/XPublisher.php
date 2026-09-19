<?php

namespace App\Services\Social\Publishers;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class XPublisher
{
    public function publish(SocialPost $post): array
    {
        $accessToken = $post->socialPage->socialAccount->access_token;

        $response = Http::withToken($accessToken)
            ->post('https://api.twitter.com/2/tweets', [
                'text' => $post->content,
            ]);

        if ($response->failed()) {
            Log::error('XPublisher: publish failed', [
                'post_id' => $post->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException("X publish failed with status {$response->status()}: {$response->body()}");
        }

        $data = $response->json() ?? [];
        $tweetId = $data['data']['id'] ?? null;

        if (empty($tweetId)) {
            Log::error('XPublisher: response missing tweet id', [
                'post_id' => $post->id,
                'body' => $response->body(),
            ]);

            throw new RuntimeException('X publish response did not include a tweet id.');
        }

        return [
            'platform_post_id' => (string) $tweetId,
            'raw_response' => $data,
        ];
    }
}
