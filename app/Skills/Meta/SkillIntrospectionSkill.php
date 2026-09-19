<?php

namespace App\Skills\Meta;

use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Models\AgentSkillAssignment;
use App\Services\AI\SkillRegistryService;
use App\Skills\BaseSkill;
use App\Skills\DTOs\SkillResult;

/**
 * SkillIntrospectionSkill — skill inventory and dynamic capability extension (Layer 6 — Meta-Agent)
 *
 * Handles the two DB-dependent meta actions extracted from SuperpowersSkill:
 *   introspect – list all skills available to this agent with health scores
 *   extend     – dynamically activate an additional skill for this deployment
 *
 * The combine/augment actions are handled by SkillCombinatorSkill.
 */
class SkillIntrospectionSkill extends BaseSkill
{
    public function key(): string
    {
        return 'skill-introspection';
    }

    public function layer(): string
    {
        return 'meta';
    }

    public function execute(array $input, array $context = []): SkillResult
    {
        $action = $input['action'] ?? 'introspect';
        $deployment = $context['deployment'] ?? null;

        return match ($action) {
            'introspect' => $this->introspect($input, $deployment),
            'extend' => $this->extend($input, $deployment),
            default => SkillResult::failed("Unknown skill-introspection action: [{$action}]"),
        };
    }

    private function introspect(?array $input, ?AgentDeployment $deployment): SkillResult
    {
        $includeDisabled = (bool) ($input['include_disabled'] ?? false);

        if (! $deployment instanceof AgentDeployment) {
            return SkillResult::failed('Deployment context required to introspect agent skills');
        }

        $query = AgentSkillAssignment::where('agent_deployment_id', $deployment->id)
            ->with('skill:id,key,name,layer,risk_level,confidence_score');

        if (! $includeDisabled) {
            $query->where('is_enabled', true);
        }

        $assignments = $query->get();

        $skills = $assignments->map(fn ($a) => [
            'key' => $a->skill?->key,
            'name' => $a->skill?->name,
            'layer' => $a->skill?->layer,
            'risk_level' => $a->skill?->risk_level,
            'confidence_score' => $a->skill?->confidence_score,
            'enabled' => $a->is_enabled,
            'has_implementation' => app(SkillRegistryService::class)->hasImplementation($a->skill->key ?? ''),
        ])->sortBy('layer')->values()->all();

        $layers = array_unique(array_column($skills, 'layer'));
        $implementedCount = count(array_filter($skills, fn ($s) => $s['has_implementation']));

        return SkillResult::completed(
            [
                'deployment_id' => $deployment->id,
                'deployment_name' => $deployment->name,
                'skill_count' => count($skills),
                'implemented_count' => $implementedCount,
                'unimplemented_count' => count($skills) - $implementedCount,
                'layers_active' => array_values($layers),
                'skills' => $skills,
                'capability_coverage' => count($skills) > 0 ? round($implementedCount / count($skills) * 100, 1) : 0,
            ],
            100.0
        );
    }

    private function extend(?array $input, ?AgentDeployment $deployment): SkillResult
    {
        if (! $deployment instanceof AgentDeployment) {
            return SkillResult::failed('Deployment context required to extend agent capabilities');
        }

        $skillKey = $input['skill_key'] ?? '';

        if (empty($skillKey)) {
            return SkillResult::failed('skill_key is required for extend action');
        }

        $skill = AgentSkill::where('key', $skillKey)->where('is_active', true)->first();

        if (! $skill) {
            return SkillResult::failed("Skill '{$skillKey}' not found in the catalogue or is inactive");
        }

        if (in_array($skill->risk_level, ['critical', 'high'], true)) {
            return SkillResult::failed(
                "Skill '{$skillKey}' has risk level '{$skill->risk_level}' — human approval required to activate"
            );
        }

        $existing = AgentSkillAssignment::where('agent_deployment_id', $deployment->id)
            ->where('skill_id', $skill->id)
            ->first();

        if ($existing) {
            if ($existing->is_enabled) {
                return SkillResult::skipped("Skill '{$skillKey}' is already enabled on this deployment");
            }
            $existing->update(['is_enabled' => true]);
        } else {
            AgentSkillAssignment::create([
                'agent_deployment_id' => $deployment->id,
                'skill_id' => $skill->id,
                'organization_id' => $deployment->organization_id,
                'is_enabled' => true,
                'assigned_by' => 'superpowers_skill',
            ]);
        }

        return SkillResult::completed(
            [
                'activated_skill' => $skillKey,
                'skill_name' => $skill->name,
                'layer' => $skill->layer,
                'deployment_id' => $deployment->id,
                'status' => 'activated',
            ],
            92.0,
            [],
            ['Verify the newly activated skill with an introspect call before relying on it in tasks']
        );
    }
}
