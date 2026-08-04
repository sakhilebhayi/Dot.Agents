<?php

namespace App\Livewire\Social;

use App\Actions\Social\ApproveSocialPostAction;
use App\Actions\Social\ScheduleSocialPostAction;
use App\DTOs\Social\SocialPostData;
use App\Models\SocialPost;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class SocialPostManager extends Component
{
    use WithPagination;

    public string $filter = 'pending';           // pending|scheduled|published|draft

    public string $platform = 'all';

    // Compose form
    public ?int $composingPageId = null;

    public string $composingContent = '';

    public string $composingPostType = 'post';

    public ?string $composingScheduledAt = null;

    public bool $showCompose = false;

    #[Computed]
    public function orgId(): int
    {
        return (int) session('current_organization_id');
    }

    #[Computed]
    public function posts()
    {
        // No explicit organization_id filter needed: SocialPost's
        // HasOrganizationScope trait applies it automatically from the session.
        $query = SocialPost::with(['socialPage.socialAccount', 'approvedBy']);

        if ($this->filter === 'pending') {
            $query->where('approval_status', 'pending');
        } elseif ($this->filter !== 'all') {
            $query->where('status', $this->filter);
        }

        if ($this->platform !== 'all') {
            $query->whereHas('socialPage.socialAccount', fn ($q) => $q->where('platform', $this->platform));
        }

        return $query->orderByDesc('created_at')->paginate(20);
    }

    #[Computed]
    public function pendingCount(): int
    {
        // No explicit organization_id filter needed: SocialPost's
        // HasOrganizationScope trait applies it automatically from the session.
        return SocialPost::where('approval_status', 'pending')->count();
    }

    public function schedulePost(): void
    {
        $this->validate([
            'composingPageId' => 'required|integer',
            'composingContent' => 'required|string|max:10000',
            'composingPostType' => 'required|string',
            'composingScheduledAt' => 'nullable|date|after:now',
        ]);

        $data = SocialPostData::fromArray([
            'organization_id' => $this->orgId,
            'social_page_id' => $this->composingPageId,
            'content' => $this->composingContent,
            'post_type' => $this->composingPostType,
            'scheduled_at' => $this->composingScheduledAt,
            'requires_approval' => true,
        ]);

        app(ScheduleSocialPostAction::class)->execute($data, auth()->id());

        $this->reset(['composingPageId', 'composingContent', 'composingScheduledAt', 'showCompose']);
        $this->dispatch('post-scheduled');
    }

    public function approvePost(int $postId): void
    {
        $post = SocialPost::where('organization_id', $this->orgId)->findOrFail($postId);

        $this->authorize('approve', $post);

        app(ApproveSocialPostAction::class)->execute($post, auth()->id());

        $this->dispatch('post-approved');
    }

    public function render()
    {
        return view('livewire.social.social-post-manager');
    }
}
