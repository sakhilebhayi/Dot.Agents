<?php

namespace App\Services\Workflows;

use App\Actions\Agents\StartRunAction;
use App\DTOs\Agents\StartRunData;
use App\Exceptions\RunRefused;
use App\Models\Agent;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use App\Services\Approvals\ApprovalDesk;
use App\Support\MarkedData;
use Illuminate\Support\Str;

/** Starts one step. An agent step and a tool step are ordinary runs, so every limit and gate applies to them. */
class StepStarter
{
    public function __construct(private readonly StartRunAction $runs, private readonly ApprovalDesk $desk) {}

    /**
     * @param  array<string, mixed>  $node
     * @param  list<WorkflowStep>  $parents  the steps whose lines led here
     */
    public function start(WorkflowExecution $execution, WorkflowStep $step, array $node, array $parents): void
    {
        $input = $this->input($execution, $node, $parents);

        $step->update(['status' => WorkflowStep::RUNNING, 'input' => $input, 'attempts' => $step->attempts + 1, 'started_at' => $step->started_at ?? now(), 'error_message' => null]);

        if ($node['kind'] === 'approval') {
            $this->approval($execution, $step, $parents);

            return;
        }

        try {
            $run = $this->runs->execute($this->agent($execution, $node), new StartRunData(
                input: $input,
                triggerType: $node['kind'] === 'tool' ? 'workflow_tool' : 'workflow',
                triggerRef: (string) $step->id,
                initiatedBy: $execution->triggered_by,
                workflowExecutionId: $execution->id,
                isTest: $execution->is_test,
                taint: collect($parents)->reduce(fn (int $taint, WorkflowStep $parent) => $taint | (int) $parent->run?->taint, 0),
                directCall: $node['kind'] === 'tool' ? $this->call($node, $parents) : null,
            ));
        } catch (RunRefused $e) {
            $step->update(['status' => WorkflowStep::FAILED, 'error_message' => $e->getMessage(), 'finished_at' => now()]);

            return;
        }

        $step->update(['agent_run_id' => $run->id]);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<WorkflowStep>  $parents
     */
    private function input(WorkflowExecution $execution, array $node, array $parents): string
    {
        $parts = [trim((string) ($node['config']['instruction'] ?? '')) ?: "Carry out this step: {$node['label']}."];

        if (filled($execution->input_data['text'] ?? null)) {
            $parts[] = "The request this work began with:\n".MarkedData::wrap('request', $execution->input_data['text']);
        }

        foreach ($parents as $parent) {
            $parts[] = "From the step \"{$parent->label}\":\n".MarkedData::wrap('step', (string) ($parent->output ?? $parent->error_message));
        }

        return implode("\n\n", $parts);
    }

    /** @param  array<string, mixed>  $node */
    private function agent(WorkflowExecution $execution, array $node): AgentDeployment
    {
        $id = $node['config']['deployment_id'] ?? null;

        $deployment = $id !== null
            ? AgentDeployment::where('organization_id', $execution->organization_id)->find($id)
            : AgentDeployment::where('organization_id', $execution->organization_id)->where('agent_id', Agent::where('slug', $node['agent_key'])->value('id'))->first();

        return $deployment ?? throw new RunRefused("The agent for the step \"{$node['label']}\" no longer exists.");
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<WorkflowStep>  $parents
     * @return array{name: string, arguments: array<string, mixed>}
     */
    private function call(array $node, array $parents): array
    {
        $previous = (string) ($parents[0]->output ?? '');

        return [
            'name' => (string) ($node['config']['tool'] ?? ''),
            'arguments' => collect((array) ($node['config']['arguments'] ?? []))->map(fn ($value) => is_string($value) ? str_replace('{previous}', $previous, $value) : $value)->all(),
        ];
    }

    /** @param  list<WorkflowStep>  $parents */
    private function approval(WorkflowExecution $execution, WorkflowStep $step, array $parents): void
    {
        $approval = AgentApproval::create([
            'workflow_step_id' => $step->id,
            'organization_id' => $execution->organization_id,
            'agent_deployment_id' => $parents[0]->run?->agent_deployment_id,
            'requested_from' => $execution->workflow->created_by,
            'approval_type' => 'Workflow',
            'title' => $step->label,
            'description' => 'The workflow continues only if you approve.',
            'proposed_action' => [
                'agent' => $execution->workflow->name,
                'what' => $step->label,
                'values' => collect($parents)->mapWithKeys(fn (WorkflowStep $parent) => [$parent->label => Str::limit((string) $parent->output, 1500)])->all(),
                'consequence' => 'The workflow continues only if you approve.',
                'permissions' => 'Whatever the next steps of this workflow do.',
                'if_rejected' => 'The workflow stops here and says so.',
                'held_because' => 'This workflow has an approval step here.',
                'outside_content' => collect($parents)->contains(fn (WorkflowStep $parent) => ((int) $parent->run?->taint & 1) === 1),
                'agent_says' => null,
            ],
            'risk_level' => 'medium',
            'gate_code' => 'workflow_step',
            'status' => 'pending',
            'expires_at' => now()->addHours(72),
        ]);

        $this->desk->record($approval, null, 'requested', 'This workflow has an approval step here.');
        $step->update(['status' => WorkflowStep::WAITING]);
    }
}
