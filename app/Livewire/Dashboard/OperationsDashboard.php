<?php

namespace App\Livewire\Dashboard;

use App\Services\Infrastructure\PlatformMetricsService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
/**
 * Platform Operations Center
 *
 * Executive-grade real-time dashboard showing:
 *  - Agent executions, failure rates, response times
 *  - Queue depth per channel
 *  - Security events, prompt injection attempts
 *  - Circuit breaker statuses per AI provider
 *  - Active deployment count
 *
 * Refreshes every 30 seconds via Livewire polling.
 * All data is cached for 60s by PlatformMetricsService.
 */
class OperationsDashboard extends Component
{
    /** Actions a read-only (viewer) role may still call; every other action is refused for it. */
    public const READ_ONLY_ACTIONS = ['refresh'];

    public string $timeframe = '24h';

    public function boot(PlatformMetricsService $metrics): void
    {
        $this->metrics = $metrics;
    }

    private PlatformMetricsService $metrics;

    #[Computed]
    public function organizationId(): ?int
    {
        return session('current_organization_id');
    }

    #[Computed]
    public function snapshot(): array
    {
        if (! $this->organizationId) {
            return [];
        }

        return $this->metrics->getOperationsSnapshot($this->organizationId);
    }

    #[Computed]
    public function agentExecutions(): array
    {
        return $this->snapshot['agent_executions'] ?? [];
    }

    #[Computed]
    public function failureRate(): float
    {
        return $this->snapshot['failure_rate'] ?? 0.0;
    }

    #[Computed]
    public function avgResponseTimeMs(): float
    {
        return $this->snapshot['avg_response_time_ms'] ?? 0.0;
    }

    #[Computed]
    public function queueDepth(): array
    {
        return $this->snapshot['queue_depth'] ?? [];
    }

    #[Computed]
    public function securityEvents(): array
    {
        return $this->snapshot['security_events'] ?? [];
    }

    #[Computed]
    public function circuitBreakers(): array
    {
        return $this->snapshot['circuit_breakers'] ?? [];
    }

    #[Computed]
    public function activeDeployments(): int
    {
        return $this->snapshot['active_deployments'] ?? 0;
    }

    /**
     * Health classification — used to drive the RAG status badge.
     * green = healthy | amber = degraded | red = critical
     */
    #[Computed]
    public function platformHealth(): string
    {
        $failureRate = $this->failureRate;
        $openCritical = $this->securityEvents['open_critical'] ?? 0;
        $queueTotal = $this->queueDepth['_total'] ?? 0;

        $openCircuits = collect($this->circuitBreakers)
            ->filter(fn ($cb) => ($cb['state'] ?? 'closed') === 'open')
            ->count();

        if ($failureRate >= 20 || $openCritical >= 5 || $openCircuits >= 2) {
            return 'red';
        }

        if ($failureRate >= 10 || $openCritical >= 1 || $queueTotal >= 500 || $openCircuits >= 1) {
            return 'amber';
        }

        return 'green';
    }

    public function refresh(): void
    {
        if ($this->organizationId) {
            app(PlatformMetricsService::class)->invalidate($this->organizationId);
        }

        unset($this->snapshot);
    }

    public function render()
    {
        return view('livewire.dashboard.operations-dashboard');
    }
}
