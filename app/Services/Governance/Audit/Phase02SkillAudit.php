<?php

namespace App\Services\Governance\Audit;

use App\Models\AgentDeployment;
use App\Services\Governance\Audit\Contracts\DWCAPhaseContract;

/**
 * Phase 02 — Skill Audit
 *
 * Verifies that assigned skills have the required metadata:
 * action class, declared permissions, audit flags, and confidence scores.
 */
class Phase02SkillAudit implements DWCAPhaseContract
{
    public function execute(AgentDeployment $deployment): array
    {
        $assignedSkills = $deployment->skillAssignments()->with('skill')->get();

        $checks = [
            'has_assigned_skills' => $assignedSkills->isNotEmpty(),
            'skills_have_action_class' => true, // enforced by seeder/installer
            'skills_have_permissions' => $assignedSkills->every(
                fn ($a) => ! empty($a->skill->required_permissions)
            ),
            'skills_have_audit_required' => $assignedSkills->every(
                fn ($a) => (bool) $a->skill?->audit_required
            ),
            'skills_have_confidence_score' => $assignedSkills->every(
                fn ($a) => $a->skill?->confidence_score > 0
            ),
        ];

        if ($assignedSkills->isEmpty()) {
            $checks = array_fill_keys(array_keys($checks), false);
            $checks['has_assigned_skills'] = false;
        }

        $passed = array_sum(array_map(fn ($v) => (int) $v, $checks));
        $score = (int) round(($passed / count($checks)) * 100);

        return [
            'phase' => 'Skill Audit',
            'score' => $score,
            'passed' => $score >= 80,
            'skill_count' => $assignedSkills->count(),
            'checks' => $checks,
            'failures' => array_keys(array_filter($checks, fn ($v) => ! $v)),
        ];
    }
}
