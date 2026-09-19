<?php

namespace App\Services\Social\Publishers;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FacebookPublisher
{
    private const API_VERSION = 'v21.0';

    public function publish(SocialPost $post): array
    {
        $pageId = $post->socialPage->platform_page_id;
        $accessToken = $post->socialPage->socialAccount->access_token;

        $response = Http::asForm()->post(
            'https://graph.facebook.com/'.self::API_VERSION."/{$pageId}/feed",
            [
                'message' => $post->content,
                'access_token' => $accessToken,
            ]
        );

        if ($response->failed()) {
            Log::error('FacebookPublisher: publish failed', [
                'post_id' => $post->id,
                'page_id' => $pageId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException("Facebook publish failed with status {$response->status()}: {$response->body()}");
        }

        $data = $response->json() ?? [];

        if (empty($data['id'])) {
            Log::error('FacebookPublisher: response missing post id', [
                'post_id' => $post->id,
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Facebook publish response did not include a post id.');
        }

        return [
            'platform_post_id' => (string) $data['id'],
            'raw_response' => $data,
        ];
    }
}
