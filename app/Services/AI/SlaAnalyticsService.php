<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AgentDeployment;
use App\Models\AgentTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SlaAnalyticsService
{
    /**
     * Whitelist of DB-driver-aware date expressions for daily grouping.
     * Values are static SQL literals — never derived from user input.
     */
    private const DATE_EXPR_BY_DRIVER = [
        'sqlite' => "strftime('%Y-%m-%d', created_at)",
        'mysql' => "DATE_FORMAT(created_at, '%Y-%m-%d')",
        'pgsql' => "TO_CHAR(created_at, 'YYYY-MM-DD')",
    ];

    public function getDeployments(int $organizationId): Collection
    {
        return AgentDeployment::where('organization_id', $organizationId)
            ->whereIn('status', ['active', 'paused'])
            ->with('agent:id,name,slug')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'agent_id', 'display_name', 'status']);
    }

    public function getSlaMetrics(int $organizationId, string $since, ?int $deploymentId): array
    {
        $query = AgentTask::where('organization_id', $organizationId)
            ->where('created_at', '>=', $since)
            ->whereNotNull('actual_duration_minutes')
            ->when($deploymentId, fn ($q) => $q->where('agent_deployment_id', $deploymentId));

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

    public function getLatencyBreakdown(int $organizationId, string $since, ?int $deploymentId): Collection
    {
        return AgentTask::where('organization_id', $organizationId)
            ->where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->whereNotNull('actual_duration_minutes')
            ->when($deploymentId, fn ($q) => $q->where('agent_deployment_id', $deploymentId))
            ->select(
                DB::raw(self::dateExpr().' as date'),
                DB::raw('AVG(actual_duration_minutes) as avg_minutes'),
                DB::raw('COUNT(*) as task_count'),
                DB::raw('AVG(confidence_score) as avg_confidence'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public function getSlaBreaches(int $organizationId, string $since, ?int $deploymentId, float $threshold): array
    {
        $base = AgentTask::where('organization_id', $organizationId)
            ->where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->when($deploymentId, fn ($q) => $q->where('agent_deployment_id', $deploymentId));

        $total = $base->count();
        $breaches = (clone $base)->where('actual_duration_minutes', '>', $threshold)->count();

        return [
            'threshold_minutes' => $threshold,
            'breach_count' => $breaches,
            'total_completed' => $total,
            'breach_rate' => $total > 0 ? round($breaches / $total * 100, 1) : 0,
        ];
    }

    private function percentile(Collection $sorted, int $pct): float
    {
        if ($sorted->isEmpty()) {
            return 0.0;
        }
        $index = (int) ceil(($pct / 100) * $sorted->count()) - 1;

        return round((float) $sorted->get(max(0, $index)), 2);
    }

    private static function dateExpr(): string
    {
        $driver = DB::getDriverName();

        return self::DATE_EXPR_BY_DRIVER[$driver]
            ?? throw new \RuntimeException("Unsupported DB driver '{$driver}' for SlaAnalyticsService.");
    }
}
