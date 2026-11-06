<?php

namespace App\Services\Infrastructure;

use App\Models\AgentDeployment;
use App\Models\AgentTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Platform Health Check Service
 *
 * Runs checks across all critical infrastructure components and returns
 * a structured health report. Used by the /health/detailed endpoint and
 * the DigitalImmuneSystem scheduled task.
 *
 * Each check returns:
 *   - status: 'healthy' | 'degraded' | 'unhealthy'
 *   - latency_ms: round-trip time in ms (where applicable)
 *   - message: human-readable status detail
 */
class HealthCheckService
{
    private const HEALTHY = 'healthy';

    private const DEGRADED = 'degraded';

    private const UNHEALTHY = 'unhealthy';

    /**
     * Run all checks and return a comprehensive health report.
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'agent_runtime' => $this->checkAgentRuntime(),
        ];

        $overallStatus = $this->resolveOverallStatus($checks);

        return [
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => $checks,
        ];
    }

    /**
     * Quick liveness check (fast, no deep inspection).
     * Used by the basic GET /up endpoint.
     */
    public function alive(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    // ── Individual Checks ─────────────────────────────────────────────────────

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            $latency = (int) ((microtime(true) - $start) * 1000);

            return [
                'status' => $latency > 500 ? self::DEGRADED : self::HEALTHY,
                'latency_ms' => $latency,
                'message' => $latency > 500
                    ? "Database responding but slow ({$latency}ms)"
                    : 'Database connection healthy',
            ];
        } catch (\Exception $e) {
            return [
                'status' => self::UNHEALTHY,
                'latency_ms' => null,
                'message' => 'Database connection failed: '.$e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        $start = microtime(true);
        $testKey = 'health_check_'.now()->timestamp;
        try {
            Cache::put($testKey, 'ok', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);
            $latency = (int) ((microtime(true) - $start) * 1000);

            if ($value !== 'ok') {
                return [
                    'status' => self::UNHEALTHY,
                    'latency_ms' => $latency,
                    'message' => 'Cache read/write mismatch',
                ];
            }

            return [
                'status' => $latency > 200 ? self::DEGRADED : self::HEALTHY,
                'latency_ms' => $latency,
                'message' => $latency > 200
                    ? "Cache responding but slow ({$latency}ms)"
                    : 'Cache (Redis) healthy',
            ];
        } catch (\Exception $e) {
            return [
                'status' => self::UNHEALTHY,
                'latency_ms' => null,
                'message' => 'Cache connection failed: '.$e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $size = Queue::size('default');

            $status = $size > 500 ? self::DEGRADED : self::HEALTHY;

            return [
                'status' => $status,
                'pending_jobs' => $size,
                'message' => $size > 500
                    ? "Queue backlog is high ({$size} pending jobs)"
                    : "Queue healthy ({$size} pending jobs)",
            ];
        } catch (\Exception $e) {
            return [
                'status' => self::UNHEALTHY,
                'pending_jobs' => null,
                'message' => 'Queue check failed: '.$e->getMessage(),
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $testPath = 'health_check/'.now()->timestamp.'.txt';
            Storage::put($testPath, 'ok');
            $content = Storage::get($testPath);
            Storage::delete($testPath);

            if ($content !== 'ok') {
                return [
                    'status' => self::UNHEALTHY,
                    'message' => 'Storage read/write mismatch',
                ];
            }

            return [
                'status' => self::HEALTHY,
                'driver' => config('filesystems.default'),
                'message' => 'Storage healthy',
            ];
        } catch (\Exception $e) {
            return [
                'status' => self::UNHEALTHY,
                'driver' => config('filesystems.default'),
                'message' => 'Storage check failed: '.$e->getMessage(),
            ];
        }
    }

    private function checkAgentRuntime(): array
    {
        try {
            $activeDeployments = AgentDeployment::where('status', 'active')->count();
            $recentFailures = AgentTask::where('status', 'failed')
                ->where('updated_at', '>=', now()->subMinutes(15))
                ->count();
            $recentTotal = AgentTask::where('updated_at', '>=', now()->subMinutes(15))->count();

            $failureRate = $recentTotal > 0
                ? ($recentFailures / $recentTotal) * 100
                : 0;

            $status = match (true) {
                $failureRate > 30 => self::UNHEALTHY,
                $failureRate > 10 => self::DEGRADED,
                default => self::HEALTHY,
            };

            return [
                'status' => $status,
                'active_deployments' => $activeDeployments,
                'recent_failure_rate_pct' => round($failureRate, 1),
                'message' => $status === self::HEALTHY
                    ? "{$activeDeployments} deployments active, failure rate {$failureRate}%"
                    : "High failure rate detected: {$failureRate}% in last 15 minutes",
            ];
        } catch (\Exception $e) {
            return [
                'status' => self::UNHEALTHY,
                'message' => 'Agent runtime check failed: '.$e->getMessage(),
            ];
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveOverallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array(self::UNHEALTHY, $statuses)) {
            return self::UNHEALTHY;
        }

        if (in_array(self::DEGRADED, $statuses)) {
            return self::DEGRADED;
        }

        return self::HEALTHY;
    }
}
