<?php

namespace App\Services\Social\Publishers;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LinkedInPublisher
{
    public function publish(SocialPost $post): array
    {
        $accessToken = $post->socialPage->socialAccount->access_token;
        $organizationUrn = 'urn:li:organization:'.$post->socialPage->platform_page_id;

        $response = Http::withToken($accessToken)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->post('https://api.linkedin.com/v2/ugcPosts', [
                'author' => $organizationUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => ['text' => $post->content],
                        'shareMediaCategory' => 'NONE',
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ]);

        if ($response->failed()) {
            Log::error('LinkedInPublisher: publish failed', [
                'post_id' => $post->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException("LinkedIn publish failed with status {$response->status()}: {$response->body()}");
        }

        $data = $response->json() ?? [];
        // LinkedIn's ugcPosts endpoint returns the created id in the JSON body
        // on some API versions and only in the x-restli-id header on others.
        $platformPostId = $data['id'] ?? $response->header('x-restli-id');

        if (empty($platformPostId)) {
            Log::error('LinkedInPublisher: response missing post id', [
                'post_id' => $post->id,
                'body' => $response->body(),
            ]);

            throw new RuntimeException('LinkedIn publish response did not include a post id.');
        }

        return [
            'platform_post_id' => (string) $platformPostId,
            'raw_response' => $data,
        ];
    }
}
