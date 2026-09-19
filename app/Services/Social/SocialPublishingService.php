<?php

namespace App\Services\Social;

use App\Models\OrganizationSocialCredential;
use App\Models\SocialPost;
use App\Services\Social\Publishers\FacebookPublisher;
use App\Services\Social\Publishers\LinkedInPublisher;
use App\Services\Social\Publishers\XPublisher;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Social Publishing Service — dispatches to platform-specific publisher
 * classes based on the connected social account's platform.
 */
class SocialPublishingService
{
    /**
     * Publish a post to the appropriate platform API.
     * Returns the platform-assigned post ID.
     */
    public function publish(SocialPost $post): string
    {
        return $this->dispatch($post)['platform_post_id'];
    }

    /**
     * Publish a post and return both the platform post ID and the raw
     * platform API response, for callers that need to persist the response.
     */
    public function publishWithResponse(SocialPost $post): array
    {
        return $this->dispatch($post);
    }

    private function dispatch(SocialPost $post): array
    {
        $platform = $post->socialPage->socialAccount->platform;

        Log::info('SocialPublishingService: publishing post', [
            'post_id' => $post->id,
            'platform' => $platform,
        ]);

        return match ($platform) {
            'facebook' => app(FacebookPublisher::class)->publish($post),
            'linkedin' => app(LinkedInPublisher::class)->publish($post),
            'twitter', 'x' => app(XPublisher::class)->publish($post),
            default => throw new RuntimeException("Publishing not yet supported for platform: {$platform}"),
        };
    }

    public function findCredential(int $organizationId, string $platform): ?OrganizationSocialCredential
    {
        return OrganizationSocialCredential::where('organization_id', $organizationId)
            ->where('platform', $platform)
            ->first();
    }
}
