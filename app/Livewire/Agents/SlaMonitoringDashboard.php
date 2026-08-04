<?php

declare(strict_types=1);

namespace App\Livewire\Agents;

use App\Models\AgentDeployment;
use App\Models\AgentTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class SlaMonitoringDashboard extends Component
{
    public string $timeframe = '7d';

    public ?int $deploymentId = null;

    public function updatedTimeframe(): void
    {
        unset($this->slaMetrics, $this->deployments, $this->latencyBreakdown);
    }

    public function updatedDeploymentId(): void
    {
        unset($this->slaMetrics, $this->latencyBreakdown);
    }

    #[Computed]
    public function organizationId(): ?int
    {
        return session('current_organization_id');
    }

    #[Computed]
    public function deployments()
    {
        // No explicit organization_id filter needed: AgentDeployment's
        // HasOrganizationScope trait applies it automatically from the session.
        return AgentDeployment::whereIn('status', ['active', 'paused'])
            ->with('agent:id,name,slug')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'agent_id', 'display_name', 'status']);
    }

    #[Computed]
    public function slaMetrics(): array
    {
        $since = $this->sinceDate();

        // No explicit organization_id filter needed: AgentTask's
        // HasOrganizationScope trait applies it automatically from the session.
        $query = AgentTask::where('created_at', '>=', $since)
            ->whereNotNull('actual_duration_minutes')
            ->when($this->deploymentId, fn ($q) => $q->where('agent_deployment_id', $this->deploymentId));

        $total = $query->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $failed = (clone $query)->where('status', 'failed')->count();

        $durations = (clone $query)
            ->where('status', 'completed')
            ->orderBy('actual_duration_minutes')
            ->pluck('actual_duration_minutes')
            ->values();

        return [
            'total_tasks' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round($completed / $total * 100, 1) : 0,
            'avg_duration_minutes' => $durations->avg() ?? 0,
            'p50_minutes' => $this->percentile($durations, 50),
            'p95_minutes' => $this->percentile($durations, 95),
            'p99_minutes' => $this->percentile($durations, 99),
            'avg_confidence' => (clone $query)->avg('confidence_score') ?? 0,
            'avg_accuracy' => (clone $query)->avg('accuracy_score') ?? 0,
        ];
    }

    #[Computed]
    public function latencyBreakdown()
    {
        $since = $this->sinceDate();

        // No explicit organization_id filter needed: AgentTask's
        // HasOrganizationScope trait applies it automatically from the session.
        return AgentTask::where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->whereNotNull('actual_duration_minutes')
            ->when($this->deploymentId, fn ($q) => $q->where('agent_deployment_id', $this->deploymentId))
            ->select(
                DB::raw("strftime('%Y-%m-%d', created_at) as date"),
                DB::raw('AVG(actual_duration_minutes) as avg_minutes'),
                DB::raw('COUNT(*) as task_count'),
                DB::raw('AVG(confidence_score) as avg_confidence'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function slaBreaches(): array
    {
        $threshold = (float) config('ai.sla_threshold_minutes', 10.0);
        $since = $this->sinceDate();

        // No explicit organization_id filter needed: AgentTask's
        // HasOrganizationScope trait applies it automatically from the session.
        $breaches = AgentTask::where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->where('actual_duration_minutes', '>', $threshold)
            ->when($this->deploymentId, fn ($q) => $q->where('agent_deployment_id', $this->deploymentId))
            ->count();

        $total = AgentTask::where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->when($this->deploymentId, fn ($q) => $q->where('agent_deployment_id', $this->deploymentId))
            ->count();

        return [
            'threshold_minutes' => $threshold,
            'breach_count' => $breaches,
            'total_completed' => $total,
            'breach_rate' => $total > 0 ? round($breaches / $total * 100, 1) : 0,
        ];
    }

    private function sinceDate(): string
    {
        return match ($this->timeframe) {
            '24h' => now()->subDay()->toDateTimeString(),
            '30d' => now()->subDays(30)->toDateTimeString(),
            '90d' => now()->subDays(90)->toDateTimeString(),
            default => now()->subDays(7)->toDateTimeString(),
        };
    }

    private function percentile(Collection $sorted, int $pct): float
    {
        if ($sorted->isEmpty()) {
            return 0.0;
        }
        $index = (int) ceil(($pct / 100) * $sorted->count()) - 1;

        return round((float) $sorted->get(max(0, $index)), 2);
    }

    public function render()
    {
        return view('livewire.agents.sla-monitoring-dashboard');
    }
}
