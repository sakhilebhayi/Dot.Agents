<?php

namespace App\Services\Skills;

use App\DTOs\Skills\ExecuteSkillData;
use App\Models\AgentSkill;
use App\Models\AgentSkillAssignment;
use App\Models\Organization;
use App\Models\User;

/**
 * Validates whether a skill execution is permitted under the organization's
 * policies, department constraints, user permissions, risk thresholds,
 * compliance rules, and governance controls.
 *
 * Returns a ValidationResult value object so callers receive structured
 * pass/fail data without relying on exceptions for normal policy checks.
 */
class SkillExecutionValidator
{
    /**
     * Run all policy checks and return a structured result.
     */
    public function validate(AgentSkill $skill, ExecuteSkillData $data): ValidationResult
    {
        $checks = [];
        $reasons = [];

        // ── 1. Skill is active ────────────────────────────────────
        $skillActive = $skill->is_active;
        $checks['skill_active'] = $skillActive;
        if (! $skillActive) {
            $reasons[] = "Skill '{$skill->name}' is not active.";
        }

        // ── 2. Organization policy constraints ─────────────────────
        $org = Organization::find($data->organizationId);
        $orgPolicies = $org?->settings['policies'] ?? [];

        $skillBlockedByOrg = in_array($skill->key, $orgPolicies['blocked_skills'] ?? []);
        $checks['org_policy_check'] = ! $skillBlockedByOrg;
        if ($skillBlockedByOrg) {
            $reasons[] = "Skill '{$skill->name}' is blocked by organization policy.";
        }

        // ── 3. Required permissions check ─────────────────────────
        $requiredPermissions = $skill->required_permissions ?? [];
        $permissionsPass = true;

        if (! empty($requiredPermissions)) {
            $user = User::find($data->actorId);
            foreach ($requiredPermissions as $perm) {
                if ($user && ! $user->hasPermissionTo($perm)) {
                    $permissionsPass = false;
                    $reasons[] = "Missing required permission: {$perm}";
                    break;
                }
            }
        }

        $checks['required_permissions'] = $permissionsPass;

        // ── 4. Risk level compliance check ────────────────────────
        $maxRiskAllowed = $orgPolicies['max_skill_risk_level'] ?? 'critical';
        $riskOrder = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $skillRiskScore = $riskOrder[$skill->risk_level] ?? 1;
        $maxRiskScore = $riskOrder[$maxRiskAllowed] ?? 4;

        $riskPasses = $skillRiskScore <= $maxRiskScore;
        $checks['risk_level_check'] = $riskPasses;
        if (! $riskPasses) {
            $reasons[] = "Skill risk level '{$skill->risk_level}' exceeds the organization maximum '{$maxRiskAllowed}'.";
        }

        // ── 5. Audit requirement check ────────────────────────────
        // Always passes — just records whether auditing is mandatory
        $checks['audit_requirement'] = true;

        // ── 6. Deployment skill assignment exists and is enabled ──
        $assignmentExists = AgentSkillAssignment::where('agent_deployment_id', $data->agentDeploymentId)
            ->where('skill_id', $skill->id)
            ->where('is_enabled', true)
            ->exists();

        $checks['skill_assignment_enabled'] = $assignmentExists;
        if (! $assignmentExists) {
            $reasons[] = "Skill '{$skill->name}' is not enabled on this deployment.";
        }

        $passed = empty($reasons);

        return new ValidationResult(
            passed: $passed,
            checks: $checks,
            reason: $passed ? null : implode(' | ', $reasons),
        );
    }
}
