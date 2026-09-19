<?php

namespace App\Services\Governance\Audit;

use App\Models\AgentDeployment;
use App\Services\Governance\Audit\Contracts\DWCAPhaseContract;

/**
 * Phase 01 — Agent Discovery
 *
 * Validates that the agent has all required metadata filled in:
 * department, skills, capabilities, governance config, scorecard KPIs,
 * version, description, and deployment mode.
 */
class Phase01AgentDiscovery implements DWCAPhaseContract
{
    public function execute(AgentDeployment $deployment): array
    {
        $agent = $deployment->agent;

        $checks = [
            'has_department' => ! empty($agent->department_id),
            'has_skills' => ! empty($agent->skills),
            'has_capabilities' => ! empty($agent->capabilities),
            'has_governance_config' => ! empty($agent->risk_controls),
            'has_scorecard_config' => ! empty($agent->kpis),
            'has_version' => ! empty($agent->version),
            'has_description' => ! empty($agent->description),
            'has_deployment_mode' => ! empty($agent->default_deployment_mode),
        ];

        $passed = array_sum(array_map(fn ($v) => (int) $v, $checks));
        $score = (int) round(($passed / count($checks)) * 100);

        return [
            'phase' => 'Agent Discovery',
            'score' => $score,
            'passed' => $score >= 80,
            'checks' => $checks,
            'failures' => array_keys(array_filter($checks, fn ($v) => ! $v)),
        ];
    }
}
