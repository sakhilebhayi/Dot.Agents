<?php

namespace App\Livewire\Social;

use App\Actions\Social\MarkEscalationHandledAction;
use App\DTOs\Social\MarkEscalationHandledData;
use App\Livewire\Concerns\ResolvesCurrentOrganization;
use App\Models\SocialSentimentScore;
use App\Services\Social\ReputationMonitoringService;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SentimentMonitor extends Component
{
    use ResolvesCurrentOrganization;

    public string $timeframe = '24h';

    public string $platform = 'all';

    #[Computed]
    public function orgId(): int
    {
        return (int) session('current_organization_id');
    }

    #[Computed]
    public function sentimentBreakdown(): array
    {
        $since = $this->getSince();
        $sentiments = ['positive', 'neutral', 'concerned', 'frustrated', 'angry'];

        // No explicit organization_id filter needed: SocialSentimentScore's
        // HasOrganizationScope trait applies it automatically from the session.
        $query = SocialSentimentScore::where('scored_at', '>=', $since);

        if ($this->platform !== 'all') {
            $query->where('platform', $this->platform);
        }

        $counts = $query->selectRaw('sentiment, COUNT(*) as count')
            ->groupBy('sentiment')
            ->pluck('count', 'sentiment')
            ->toArray();

        return collect($sentiments)->mapWithKeys(fn ($s) => [$s => $counts[$s] ?? 0])->toArray();
    }

    #[Computed]
    public function unhandledEscalations()
    {
        // No explicit organization_id filter needed: SocialSentimentScore's
        // HasOrganizationScope trait applies it automatically from the session.
        return SocialSentimentScore::where('requires_escalation', true)
            ->where('escalation_handled', false)
            ->with(['socialConversation', 'socialAccount'])
            ->orderByDesc('scored_at')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function brandHealthScore(): float
    {
        return app(ReputationMonitoringService::class)
            ->calculateBrandHealthScore($this->orgId);
    }

    #[Computed]
    public function averageSentimentScore(): float
    {
        $since = $this->getSince();

        // No explicit organization_id filter needed: SocialSentimentScore's
        // HasOrganizationScope trait applies it automatically from the session.
        return (float) SocialSentimentScore::where('scored_at', '>=', $since)
            ->avg('score') ?? 50.0;
    }

    public function markHandled(int $scoreId): void
    {
        // Guard before writing: without this, a null session org context
        // would silently record the action against organization_id => 0.
        $orgId = $this->requireCurrentOrganizationId();

        app(MarkEscalationHandledAction::class)->execute(MarkEscalationHandledData::from($orgId, $scoreId));

        $this->dispatch('escalation-handled');
    }

    public function setTimeframe(string $timeframe): void
    {
        $this->timeframe = $timeframe;
    }

    private function getSince(): Carbon
    {
        return match ($this->timeframe) {
            '1h' => now()->subHour(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };
    }

    public function render()
    {
        return view('livewire.social.sentiment-monitor');
    }
}
