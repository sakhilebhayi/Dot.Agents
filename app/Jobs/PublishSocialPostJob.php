<?php

namespace App\Jobs;

use App\Events\SocialPostPublished;
use App\Models\SocialPost;
use App\Services\Governance\AuditService;
use App\Services\Social\SocialPublishingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishSocialPostJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 120;

    public function __construct(
        public readonly SocialPost $post
    ) {
        $this->onQueue('social-commerce');
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping("social-post-{$this->post->id}")];
    }

    public function handle(SocialPublishingService $publisher, AuditService $auditService): void
    {
        if ($this->post->status === 'published') {
            return;
        }

        if ($this->post->approval_status !== 'approved') {
            Log::warning('PublishSocialPostJob: post not approved', ['post_id' => $this->post->id]);

            return;
        }

        try {
            $result = $publisher->publishWithResponse($this->post);

            $this->post->update([
                'status' => 'published',
                'published_at' => now(),
                'platform_post_id' => $result['platform_post_id'],
                'platform_response' => $result['raw_response'],
            ]);

            event(new SocialPostPublished($this->post));

            $auditService->logUserAction(
                event: 'social_post.published',
                description: 'Social post published via scheduled job',
                subject: $this->post,
                metadata: ['platform' => $this->post->socialPage?->socialAccount?->platform],
            );
        } catch (Throwable $e) {
            $this->post->update(['status' => 'failed']);
            Log::error('PublishSocialPostJob failed', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
