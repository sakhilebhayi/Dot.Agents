<?php

namespace App\Livewire\Social;

use App\Models\SocialLead;
use App\Models\SocialPost;
use App\Services\Social\ReputationMonitoringService;
use App\Services\Social\SocialCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SocialDashboard extends Component
{
    public string $timeframe = '30d';

    #[Computed]
    public function orgId(): int
    {
        return (int) session('current_organization_id');
    }

    #[Computed]
    public function scorecard(): array
    {
        $days = match ($this->timeframe) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        return $this->socialCommerce()->getScorecard($this->orgId, $days);
    }

    #[Computed]
    public function urgentConversations()
    {
        return $this->socialCommerce()->getUrgentConversations($this->orgId);
    }

    #[Computed]
    public function hotLeads()
    {
        return SocialLead::where('organization_id', $this->orgId)
            ->where(fn ($q) => $q->where('priority', 'hot')->orWhere('lead_score', '>=', 80))
            ->whereIn('status', ['new', 'contacted', 'qualified'])
            ->orderByDesc('lead_score')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function pendingPosts()
    {
        return SocialPost::where('organization_id', $this->orgId)
            ->where('approval_status', 'pending')
            ->with(['socialPage.socialAccount'])
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function brandHealthScore(): float
    {
        return app(ReputationMonitoringService::class)
            ->calculateBrandHealthScore($this->orgId);
    }

    public function setTimeframe(string $timeframe): void
    {
        $this->timeframe = $timeframe;
    }

    private function socialCommerce(): SocialCommerceService
    {
        return app(SocialCommerceService::class);
    }

    public function render()
    {
        return view('livewire.social.social-dashboard');
    }
}
