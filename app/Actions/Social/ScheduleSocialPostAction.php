<?php

namespace App\Actions\Social;

use App\DTOs\Social\SocialPostData;
use App\Events\SocialPostScheduled;
use App\Jobs\PublishSocialPostJob;
use App\Models\SocialPost;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class ScheduleSocialPostAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function execute(SocialPostData $data, int $actorId): SocialPost
    {
        Gate::authorize('create', [SocialPost::class, $data->organizationId]);

        $isScheduled = $data->scheduledAt !== null && $data->scheduledAt > now();
        $status = $data->requiresApproval ? 'draft' : ($isScheduled ? 'scheduled' : 'draft');
        $approvalStatus = $data->requiresApproval ? 'pending' : 'approved';

        $post = SocialPost::create([
            ...$data->toArray(),
            'status' => $status,
            'approval_status' => $approvalStatus,
        ]);

        if (! $data->requiresApproval && $isScheduled) {
            PublishSocialPostJob::dispatch($post)
                ->delay($data->scheduledAt);
        }

        $this->auditService->logUserAction(
            event: 'social_post.scheduled',
            description: 'Social post scheduled for approval',
            data: [
                'requires_approval' => $data->requiresApproval,
            ],
            subject: $post,
        );

        event(new SocialPostScheduled($post));

        return $post;
    }
}
