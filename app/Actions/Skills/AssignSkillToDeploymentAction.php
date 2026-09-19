<?php

namespace App\Actions\Skills;

use App\DTOs\Skills\AssignSkillData;
use App\Events\SkillAssigned;
use App\Models\AgentSkill;
use App\Models\AgentSkillAssignment;
use App\Models\Scopes\SkillOrganizationScope;
use Illuminate\Support\Facades\Gate;

class AssignSkillToDeploymentAction
{
    /**
     * Assign (or re-enable) a skill on an agent deployment.
     * Idempotent: calling it a second time updates the config.
     */
    public function execute(AssignSkillData $data): AgentSkillAssignment
    {
        Gate::authorize('create', [AgentSkillAssignment::class, $data->organizationId]);

        $skill = AgentSkill::withoutGlobalScope(SkillOrganizationScope::class)->findOrFail($data->skillId);

        // A skill is either platform-wide (organization_id null) or a
        // specific org's own private custom skill — never assignable
        // outside that org.
        abort_unless(
            $skill->organization_id === null || $skill->organization_id === $data->organizationId,
            404
        );

        // Verify the skill is active and available
        abort_unless($skill->is_active, 422, "Skill [{$skill->name}] is not currently active.");

        $assignment = AgentSkillAssignment::updateOrCreate(
            [
                'agent_deployment_id' => $data->agentDeploymentId,
                'skill_id' => $data->skillId,
            ],
            [
                'organization_id' => $data->organizationId,
                'is_enabled' => $data->isEnabled,
                'config' => $data->config ?: null,
            ]
        );

        event(new SkillAssigned($assignment));

        return $assignment;
    }
}
