<?php

namespace App\Actions\Social;

use App\Events\SocialPostApproved;
use App\Jobs\PublishSocialPostJob;
use App\Models\SocialPost;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class ApproveSocialPostAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function execute(SocialPost $post, int $approverId): SocialPost
    {
        Gate::authorize('update', $post);

        $post->update([
            'approval_status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'status' => $post->scheduled_at && $post->scheduled_at > now() ? 'scheduled' : $post->status,
        ]);

        if ($post->scheduled_at && $post->scheduled_at > now()) {
            PublishSocialPostJob::dispatch($post)
                ->delay($post->scheduled_at);
        }

        $this->auditService->logUserAction(
            event: 'social_post.approved',
            description: 'Social post approved for publishing',
            subject: $post,
        );

        event(new SocialPostApproved($post->fresh(), $approverId));

        return $post->fresh();
    }
}
