<?php

namespace App\Actions\Skills;

use App\DTOs\Skills\ExecuteSkillData;
use App\Events\SkillApprovalRequested;
use App\Events\SkillExecuted;
use App\Events\SkillExecutionBlocked;
use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Models\AgentSkillApproval;
use App\Models\AgentSkillAudit;
use App\Models\AgentSkillExecution;
use App\Models\Scopes\SkillOrganizationScope;
use App\Services\Skills\SkillExecutionValidator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ExecuteSkillAction
{
    public function __construct(
        private readonly SkillExecutionValidator $validator
    ) {}

    /**
     * Execute a skill on behalf of an agent deployment.
     *
     * Returns the execution record. If approval is required, the execution
     * is created in 'pending' status and an approval record is created.
     */
    public function execute(ExecuteSkillData $data): AgentSkillExecution
    {
        // Fetched explicitly with its real organization_id regardless of
        // session context — AgentSkillPolicy::execute() is the source of
        // truth for whether this org may use this skill, not this fetch.
        $skill = AgentSkill::withoutGlobalScope(SkillOrganizationScope::class)->findOrFail($data->skillId);
        $deployment = AgentDeployment::findOrFail($data->agentDeploymentId);

        Gate::authorize('execute', [$skill, $deployment]);

        // ── 1. Policy validation ──────────────────────────────────
        $validationResult = $this->validator->validate($skill, $data);

        if (! $validationResult->passed) {
            // Record immutable block audit
            AgentSkillAudit::create([
                'skill_id' => $skill->id,
                'agent_deployment_id' => $data->agentDeploymentId,
                'organization_id' => $data->organizationId,
                'actor_id' => $data->actorId,
                'event_type' => AgentSkillAudit::EVENT_BLOCKED,
                'outcome' => AgentSkillAudit::OUTCOME_BLOCKED,
                'policy_checks' => $validationResult->checks,
                'reason' => $validationResult->reason,
                'occurred_at' => now(),
            ]);

            event(new SkillExecutionBlocked($skill, $deployment, $validationResult->reason, $data->organizationId));

            abort(403, $validationResult->reason);
        }

        // ── 2. Approval gate ─────────────────────────────────────
        if ($skill->approval_required) {
            return $this->createPendingApproval($skill, $data);
        }

        // ── 3. Create execution record ────────────────────────────
        $execution = AgentSkillExecution::create([
            'uuid' => (string) Str::uuid(),
            'skill_id' => $skill->id,
            'agent_deployment_id' => $data->agentDeploymentId,
            'organization_id' => $data->organizationId,
            'task_id' => $data->taskId,
            'trigger' => $data->trigger,
            'status' => 'running',
            'input' => $data->input,
            'confidence' => $skill->confidence_score,
            'executed_at' => now(),
        ]);

        // ── 4. Audit the execution ────────────────────────────────
        if ($skill->audit_required) {
            AgentSkillAudit::create([
                'skill_id' => $skill->id,
                'execution_id' => $execution->id,
                'agent_deployment_id' => $data->agentDeploymentId,
                'organization_id' => $data->organizationId,
                'actor_id' => $data->actorId,
                'event_type' => AgentSkillAudit::EVENT_EXECUTED,
                'outcome' => AgentSkillAudit::OUTCOME_SUCCESS,
                'policy_checks' => $validationResult->checks,
                'confidence_at_execution' => $skill->confidence_score,
                'occurred_at' => now(),
            ]);
        }

        event(new SkillExecuted($execution));

        return $execution;
    }

    private function createPendingApproval(AgentSkill $skill, ExecuteSkillData $data): AgentSkillExecution
    {
        // Create the execution in pending state
        $execution = AgentSkillExecution::create([
            'uuid' => (string) Str::uuid(),
            'skill_id' => $skill->id,
            'agent_deployment_id' => $data->agentDeploymentId,
            'organization_id' => $data->organizationId,
            'task_id' => $data->taskId,
            'trigger' => $data->trigger,
            'status' => 'pending',
            'input' => $data->input,
            'executed_at' => null,
        ]);

        // Create the approval request
        $approval = AgentSkillApproval::create([
            'skill_id' => $skill->id,
            'execution_id' => $execution->id,
            'agent_deployment_id' => $data->agentDeploymentId,
            'organization_id' => $data->organizationId,
            'requested_by' => $data->actorId,
            'status' => AgentSkillApproval::STATUS_PENDING,
            'risk_level' => $skill->risk_level,
            'context' => $data->input,
            'justification' => $data->justification,
            'expires_at' => now()->addHours(48),
        ]);

        // Audit the approval request
        AgentSkillAudit::create([
            'skill_id' => $skill->id,
            'execution_id' => $execution->id,
            'agent_deployment_id' => $data->agentDeploymentId,
            'organization_id' => $data->organizationId,
            'actor_id' => $data->actorId,
            'event_type' => AgentSkillAudit::EVENT_APPROVAL_REQUIRED,
            'outcome' => AgentSkillAudit::OUTCOME_PENDING_APPROVAL,
            'reason' => "Skill '{$skill->name}' requires approval (risk: {$skill->risk_level})",
            'occurred_at' => now(),
        ]);

        event(new SkillApprovalRequested($approval));

        return $execution;
    }
}
