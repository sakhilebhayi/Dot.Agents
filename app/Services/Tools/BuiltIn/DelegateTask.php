<?php

namespace App\Services\Tools\BuiltIn;

use App\Actions\Agents\StartRunAction;
use App\Contracts\AgentTool;
use App\DTOs\Agents\StartRunData;
use App\Exceptions\RunRefused;
use App\Models\AgentRun;
use App\Models\AgentTeamMember;
use App\Services\Tools\ToolContext;
use App\Services\Tools\ToolDefinition;
use App\Services\Tools\ToolFailure;
use App\Services\Tools\ToolOutput;
use App\Services\Tools\ToolSuspended;

/**
 * A supervisor hands part of the work to a teammate and waits. The teammate works under its own setup and limits, and
 * starts with everything the supervisor has been exposed to, so handing work over launders nothing.
 */
class DelegateTask implements AgentTool
{
    private const MAX_DEPTH = 3;

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            key: 'delegate_task',
            label: 'Hand work to a teammate',
            category: 'Productivity',
            description: 'Gives one clear piece of work to a teammate by id and waits for the result. Your team is listed in your task.',
            accesses: 'The agents in this agent\'s team.',
            schema: ['type' => 'object', 'properties' => ['teammate' => ['type' => 'integer'], 'task' => ['type' => 'string', 'maxLength' => 8000]], 'required' => ['teammate', 'task']],
            safeToRepeat: false,
        );
    }

    public function run(ToolContext $context, array $arguments): ToolOutput
    {
        $run = $context->run;
        $member = AgentTeamMember::whereHas('team', fn ($team) => $team->where('supervisor_deployment_id', $run->agent_deployment_id))
            ->where('agent_deployment_id', $arguments['teammate'])->first();

        if ($member === null) {
            throw new ToolFailure('That agent is not on this team.');
        }

        $this->assertCanGoDeeper($run, $member->agent_deployment_id);

        try {
            $child = app(StartRunAction::class)->execute($member->deployment, new StartRunData(
                input: $arguments['task'],
                triggerType: 'delegation',
                triggerRef: $context->idempotencyKey,
                initiatedBy: $run->initiated_by,
                parentRunId: $run->id,
                isTest: $run->is_test,
                taint: $run->taint,
                idempotencyKey: 'delegate:'.$context->idempotencyKey,
            ));
        } catch (RunRefused $e) {
            throw new ToolFailure($e->getMessage());
        }

        throw new ToolSuspended($child->id);
    }

    private function assertCanGoDeeper(AgentRun $run, int $teammate): void
    {
        $seen = [];

        for ($ancestor = $run; $ancestor !== null; $ancestor = $ancestor->parent_run_id === null ? null : AgentRun::find($ancestor->parent_run_id)) {
            $seen[] = $ancestor->agent_deployment_id;
        }

        if (count($seen) >= self::MAX_DEPTH) {
            throw new ToolFailure('This work has been handed on too many times.');
        }

        if (in_array($teammate, $seen, true)) {
            throw new ToolFailure('That agent is already part of this chain of work.');
        }
    }
}
