<?php

namespace App\Services\Governance;

use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\DecisionLog;
use App\Models\UsageRecord;
use Illuminate\Support\Facades\Cache;

/**
 * Digital Immune System
 *
 * Autonomous defense layer that monitors agent behavior,
 * detects threats, and auto-remediates where possible.
 */
class DigitalImmuneSystem
{
    private const CACHE_PREFIX = 'dis_';

    /**
     * Consecutive-detection threshold before an anomaly escalates from a
     * routine warning to a critical, quarantine-triggering event. A single
     * off day is normal variance; the same anomaly recurring this many times
     * in a row is a real pattern worth acting on.
     */
    private const ANOMALY_THRESHOLD = 3;

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Run a full health check on all active deployments in an organization.
     */
    public function runHealthCheck(int $organizationId): array
    {
        $deployments = AgentDeployment::where('organization_id', $organizationId)
            ->where('status', 'active')
            ->with(['agent', 'latestScorecard'])
            ->get();

        $report = [
            'checked_at' => now()->toIso8601String(),
            'total_agents' => $deployments->count(),
            'healthy' => 0,
            'warnings' => 0,
            'critical' => 0,
            'quarantined' => 0,
            'events' => [],
        ];

        foreach ($deployments as $deployment) {
            $status = $this->checkDeployment($deployment);
            $report[$status['health']]++;
            if (! empty($status['events'])) {
                $report['events'] = array_merge($report['events'], $status['events']);
            }
        }

        return $report;
    }

    /**
     * Check an individual deployment for anomalies.
     */
    public function checkDeployment(AgentDeployment $deployment): array
    {
        $events = [];
        $health = 'healthy';

        // 1. Check for agent drift (performance degradation)
        $driftResult = $this->detectAgentDrift($deployment);
        if ($driftResult['detected']) {
            $events[] = $driftResult;
            $health = 'warnings';
        }

        // 2. Check for high delusion rate
        $delusionResult = $this->checkDelusionRate($deployment);
        if ($delusionResult['detected']) {
            $events[] = $delusionResult;
            $health = 'critical';
            $this->handleHighDelusionRate($deployment);
        }

        // 3. Check for unusual task failure rate
        $failureResult = $this->checkFailureRate($deployment);
        if ($failureResult['detected']) {
            $events[] = $failureResult;
            $health = $health === 'healthy' ? 'warnings' : $health;
        }

        // 4. Check for excessive token usage (cost anomaly)
        $usageResult = $this->checkUsageAnomaly($deployment);
        if ($usageResult['detected']) {
            $events[] = $usageResult;
            if ($usageResult['severity'] === 'critical') {
                $health = 'critical';
                $this->quarantineDeployment($deployment, $usageResult['reason']);
            } else {
                $health = $health === 'healthy' ? 'warnings' : $health;
            }
        }

        // 5. Check for unapproved autonomous actions
        $autonomyResult = $this->checkAutonomyViolations($deployment);
        if ($autonomyResult['detected']) {
            $events[] = $autonomyResult;
            $health = 'critical';
            $this->quarantineDeployment($deployment, $autonomyResult['reason']);
        }

        return ['health' => $health, 'events' => $events, 'deployment_id' => $deployment->id];
    }

    private function detectAgentDrift(AgentDeployment $deployment): array
    {
        $cacheKey = self::CACHE_PREFIX."drift_{$deployment->id}";

        // Compare recent performance vs baseline
        $recentTasks = AgentTask::where('agent_deployment_id', $deployment->id)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(7))
            ->avg('confidence_score') ?? 0;

        $baselineConfidence = Cache::get($cacheKey.'_baseline', $recentTasks);

        // Store current as new baseline
        Cache::put($cacheKey.'_baseline', $recentTasks, now()->addDays(30));

        $drift = $baselineConfidence - $recentTasks;

        if ($drift > 15) {
            $this->auditService->logSecurityEvent(
                $deployment->organization_id,
                'agent_drift',
                'warning',
                "Agent Drift Detected: {$deployment->display_name}",
                "Confidence score dropped by {$drift} points vs baseline",
                ['drift' => $drift, 'baseline' => $baselineConfidence, 'current' => $recentTasks],
                $deployment->id
            );

            return [
                'detected' => true,
                'type' => 'agent_drift',
                'severity' => 'warning',
                'message' => "Confidence drift of {$drift} points detected",
                'recommendation' => 'Review recent task outputs for quality degradation',
            ];
        }

        return ['detected' => false];
    }

    private function checkDelusionRate(AgentDeployment $deployment): array
    {
        $highRiskCount = DecisionLog::where('agent_deployment_id', $deployment->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->where('delusion_risk_score', '>=', 70)
            ->count();

        if ($highRiskCount >= 5) {
            return [
                'detected' => true,
                'type' => 'high_delusion_rate',
                'severity' => 'critical',
                'message' => "{$highRiskCount} high-risk delusion events in last 24h",
                'recommendation' => 'Pause agent and review system prompt and model config',
                'reason' => "High delusion rate: {$highRiskCount} events",
            ];
        }

        return ['detected' => false];
    }

    private function checkFailureRate(AgentDeployment $deployment): array
    {
        $recent = AgentTask::where('agent_deployment_id', $deployment->id)
            ->where('created_at', '>=', now()->subHours(6))
            ->get();

        if ($recent->count() < 3) {
            return ['detected' => false];
        }

        $failureRate = $recent->where('status', 'failed')->count() / $recent->count();

        if ($failureRate > 0.5) {
            return [
                'detected' => true,
                'type' => 'high_failure_rate',
                'severity' => 'error',
                'message' => 'Failure rate: '.round($failureRate * 100).'% in last 6 hours',
                'recommendation' => 'Check agent configuration and model availability',
            ];
        }

        return ['detected' => false];
    }

    private function checkUsageAnomaly(AgentDeployment $deployment): array
    {
        $todayUsage = UsageRecord::where('agent_deployment_id', $deployment->id)
            ->whereDate('recorded_date', now()->toDateString())
            ->sum('total_cost');

        $streakKey = self::CACHE_PREFIX."usage_anomaly_streak_{$deployment->id}";

        // Simple threshold: $50/day per agent is flagged
        if ($todayUsage > 50) {
            $streak = Cache::get($streakKey, 0) + 1;
            Cache::put($streakKey, $streak, now()->addHours(48));

            $escalated = $streak >= self::ANOMALY_THRESHOLD;

            return [
                'detected' => true,
                'type' => 'usage_anomaly',
                'severity' => $escalated ? 'critical' : 'warning',
                'message' => "High daily cost: \${$todayUsage}".
                    ($escalated ? " ({$streak} consecutive days)" : ''),
                'recommendation' => $escalated
                    ? 'Sustained excessive cost — quarantining pending investigation'
                    : 'Review agent activity and optimize prompts',
                'reason' => $escalated ? "Sustained usage anomaly: {$streak} consecutive detections" : null,
            ];
        }

        // No anomaly today — reset the streak so isolated spikes don't accumulate forever
        Cache::forget($streakKey);

        return ['detected' => false];
    }

    private function checkAutonomyViolations(AgentDeployment $deployment): array
    {
        // Check if a non-autonomous agent is taking actions that require approval
        if ($deployment->deployment_mode !== 'autonomous' && ! $deployment->requires_human_approval) {
            $unapproved = AgentTask::where('agent_deployment_id', $deployment->id)
                ->where('created_at', '>=', now()->subHour())
                ->whereIn('task_type', ['action', 'approval_required'])
                ->where('status', 'completed')
                ->whereDoesntHave('approval', fn ($q) => $q->where('status', 'approved'))
                ->count();

            if ($unapproved > 0) {
                return [
                    'detected' => true,
                    'type' => 'autonomy_violation',
                    'severity' => 'critical',
                    'message' => "{$unapproved} actions taken without required approval",
                    'recommendation' => 'Quarantine agent pending investigation',
                    'reason' => 'Autonomy violation: unapproved actions executed',
                ];
            }
        }

        return ['detected' => false];
    }

    private function handleHighDelusionRate(AgentDeployment $deployment): void
    {
        // Increase the confidence threshold required for approval
        $deployment->update([
            'confidence_threshold' => min(95, $deployment->confidence_threshold + 15),
            'requires_human_approval' => true,
        ]);

        $this->auditService->logSecurityEvent(
            $deployment->organization_id,
            'auto_remediation',
            'warning',
            'Auto-remediation: Approval threshold increased',
            "DIS increased approval threshold for {$deployment->display_name} due to high delusion rate",
            ['new_threshold' => $deployment->confidence_threshold],
            $deployment->id
        );
    }

    public function quarantineDeployment(AgentDeployment $deployment, string $reason): void
    {
        $deployment->update(['status' => 'suspended']);

        $this->auditService->logSecurityEvent(
            $deployment->organization_id,
            'agent_quarantined',
            'critical',
            "Agent Quarantined: {$deployment->display_name}",
            "Digital Immune System quarantined agent: {$reason}",
            ['reason' => $reason],
            $deployment->id
        );
    }
}
