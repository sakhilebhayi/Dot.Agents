<?php

namespace App\Livewire\Dashboard;

use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\SecurityEvent;
use App\Models\UsageRecord;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class AgentDashboard extends Component
{
    /** Actions a read-only (viewer) role may still call; every other action is refused for it. */
    public const READ_ONLY_ACTIONS = ['setTimeframe'];

    public string $timeframe = '7d';

    #[Computed]
    public function organization()
    {
        return auth()->user()->currentTeam ?? null;
    }

    #[Computed]
    public function deploymentStats(): array
    {
        $orgId = $this->getOrgId();
        $total = AgentDeployment::where('organization_id', $orgId)->count();
        $active = AgentDeployment::where('organization_id', $orgId)->where('status', 'active')->count();
        $paused = AgentDeployment::where('organization_id', $orgId)->where('status', 'paused')->count();

        return compact('total', 'active', 'paused');
    }

    #[Computed]
    public function taskStats(): array
    {
        $orgId = $this->getOrgId();
        $since = $this->getSince();

        $query = AgentTask::where('organization_id', $orgId)->where('created_at', '>=', $since);

        return [
            'total' => $query->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'pending_approval' => (clone $query)->where('status', 'awaiting_approval')->count(),
        ];
    }

    #[Computed]
    public function pendingApprovals()
    {
        return AgentApproval::where('organization_id', $this->getOrgId())
            ->where('status', 'pending')
            ->with(['deployment.agent', 'task'])
            ->orderBy('risk_level')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function activeAgents()
    {
        return AgentDeployment::where('organization_id', $this->getOrgId())
            ->where('status', 'active')
            ->with(['agent', 'latestScorecard'])
            ->orderByDesc('last_active_at')
            ->take(6)
            ->get();
    }

    #[Computed]
    public function recentSecurityEvents()
    {
        return SecurityEvent::where('organization_id', $this->getOrgId())
            ->whereIn('severity', ['critical', 'error'])
            ->where('status', 'open')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function costStats(): array
    {
        $orgId = $this->getOrgId();
        $since = $this->getSince();

        $totalCost = UsageRecord::where('organization_id', $orgId)
            ->where('recorded_date', '>=', $since->toDateString())
            ->sum('total_cost');

        $dailyAvg = $totalCost / max(1, now()->diffInDays($since));

        return [
            'total' => round($totalCost, 2),
            'daily_avg' => round($dailyAvg, 2),
        ];
    }

    private function getOrgId(): ?int
    {
        return session('current_organization_id')
            ?? auth()->user()?->currentOrganization()?->id;
    }

    private function getSince(): Carbon
    {
        return match ($this->timeframe) {
            '24h' => now()->subDay(),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(7),
        };
    }

    public function setTimeframe(string $tf): void
    {
        $this->timeframe = $tf;
        unset($this->taskStats, $this->costStats);
    }

    public function render()
    {
        return view('livewire.dashboard.agent-dashboard');
    }
}
