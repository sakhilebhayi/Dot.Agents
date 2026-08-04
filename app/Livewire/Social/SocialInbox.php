<?php

namespace App\Livewire\Social;

use App\Actions\Social\EscalateConversationAction;
use App\Actions\Social\RespondToSocialMessageAction;
use App\DTOs\Social\SocialMessageResponseData;
use App\Livewire\Concerns\ResolvesCurrentOrganization;
use App\Models\SocialConversation;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SocialInbox extends Component
{
    use ResolvesCurrentOrganization;

    public string $filter = 'open';             // open|escalated|resolved|all

    public string $platform = 'all';

    public string $priority = 'all';

    public ?int $activeConversationId = null;

    public string $replyContent = '';

    #[Computed]
    public function orgId(): int
    {
        return (int) session('current_organization_id');
    }

    #[Computed]
    public function conversations()
    {
        // No explicit organization_id filter needed: SocialConversation's
        // HasOrganizationScope trait applies it automatically from the session.
        $query = SocialConversation::with(['socialAccount', 'agentDeployment', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at');

        if ($this->filter !== 'all') {
            $query->where('status', $this->filter);
        }
        if ($this->platform !== 'all') {
            $query->where('platform', $this->platform);
        }
        if ($this->priority !== 'all') {
            $query->where('priority', $this->priority);
        }

        return $query->paginate(20);
    }

    #[Computed]
    public function activeConversation(): ?SocialConversation
    {
        if (! $this->activeConversationId) {
            return null;
        }

        return SocialConversation::where('organization_id', $this->orgId)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at'), 'socialAccount'])
            ->find($this->activeConversationId);
    }

    public function openConversation(int $conversationId): void
    {
        $this->activeConversationId = $conversationId;
        $this->replyContent = '';
    }

    public function sendReply(): void
    {
        $this->validate(['replyContent' => 'required|string|max:4000']);

        // Guard before writing: without this, a null session org context
        // would silently persist a reply with organization_id => 0.
        $orgId = $this->requireCurrentOrganizationId();

        $data = new SocialMessageResponseData(
            organizationId: $orgId,
            socialConversationId: $this->activeConversationId,
            content: $this->replyContent,
            isAiGenerated: false,
        );

        app(RespondToSocialMessageAction::class)->execute($data, auth()->id());

        $this->replyContent = '';
        $this->dispatch('reply-sent');
    }

    public function escalate(int $conversationId): void
    {
        $conversation = SocialConversation::where('organization_id', $this->orgId)->findOrFail($conversationId);

        app(EscalateConversationAction::class)->execute(
            conversation: $conversation,
            escalatedTo: auth()->id(),
            escalatedBy: auth()->id(),
            reason: 'Manual escalation from inbox',
        );

        $this->dispatch('conversation-escalated');
    }

    public function render()
    {
        return view('livewire.social.social-inbox');
    }
}
