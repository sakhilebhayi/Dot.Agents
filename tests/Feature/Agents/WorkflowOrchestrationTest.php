<?php

namespace Tests\Feature\Agents;

use App\Actions\Agents\SaveAgentTeamAction;
use App\Actions\Agents\StartRunAction;
use App\Actions\Governance\ProcessApprovalAction;
use App\Actions\Security\EmergencyKillSwitchAction;
use App\Actions\Workflows\ControlWorkflowExecutionAction;
use App\Actions\Workflows\StartWorkflowAction;
use App\DTOs\Agents\SaveAgentTeamData;
use App\DTOs\Agents\StartRunData;
use App\DTOs\Governance\ProcessApprovalData;
use App\DTOs\Workflows\ControlExecutionData;
use App\DTOs\Workflows\StartWorkflowData;
use App\Jobs\AdvanceWorkflow;
use App\Livewire\Agents\AgentTeams;
use App\Livewire\Workflows\WorkflowBuilder;
use App\Livewire\Workflows\WorkflowExecutions;
use App\Livewire\Workflows\WorkflowList;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\AgentTeam;
use App\Models\AgentTeamMember;
use App\Models\AgentWorkflow;
use App\Models\ModelPrice;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use App\Services\Http\UrlGuard;
use App\Services\Models\ScriptedGateway;
use App\Services\Runs\DelegationReturn;
use App\Services\Runs\RunStepper;
use App\Services\Runs\RunSweeper;
use App\Services\Workflows\WorkflowConductor;
use App\Services\Workflows\WorkflowGraph;
use App\Services\Workflows\WorkflowValidator;
use App\Support\RunTaint;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    private AgentDeployment $first;

    private AgentDeployment $second;

    private AgentDeployment $third;

    private ScriptedGateway $model;

    private AgentWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        config(['prism.default_provider' => 'scripted']);
        $this->model = app(ScriptedGateway::class);
        $this->owner = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id]);
        $this->organization->users()->attach($this->owner, ['role' => 'owner']);
        session(['current_organization_id' => $this->organization->id]);
        $this->actingAs($this->owner);

        foreach (['first', 'second', 'third'] as $name) {
            $this->{$name} = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active', 'name' => ucfirst($name), 'enable_memory' => false, 'autonomy_level' => 4]);
        }

        $this->workflow = AgentWorkflow::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->owner->id]);
    }

    /** @param  array<string, mixed>  $config */
    private function node(string $id, array $config = [], ?AgentDeployment $agent = null): void
    {
        $this->workflow->nodes()->create(['uuid' => $id, 'organization_id' => $this->organization->id, 'agent_key' => 'agent', 'label' => $id, 'position_x' => 0, 'position_y' => 0, 'config' => ['deployment_id' => ($agent ?? $this->first)->id, ...$config]]);
    }

    private function line(string $from, string $to, ?array $condition = null): void
    {
        $this->workflow->connections()->create(['uuid' => (string) Str::uuid(), 'organization_id' => $this->organization->id, 'from_node_uuid' => $from, 'to_node_uuid' => $to, 'condition' => $condition]);
    }

    private function launch(string $request = 'Prepare the monthly report'): WorkflowExecution
    {
        return app(WorkflowConductor::class)->start($this->workflow, $request, $this->owner->id)->refresh();
    }

    private function finishRuns(string $output): void
    {
        AgentRun::where('status', '!=', AgentRun::COMPLETED)->get()->each->update(['status' => AgentRun::COMPLETED, 'output' => $output]);
    }

    private function step(string $id): WorkflowStep
    {
        return WorkflowStep::where('node_uuid', $id)->firstOrFail();
    }

    public function test_steps_run_in_order_and_each_is_given_what_the_last_one_made(): void
    {
        $this->node('collect');
        $this->node('report', ['instruction' => 'Write it up.'], $this->second);
        $this->line('collect', 'report');
        $this->model->say('Sales were 40.')->say('Report: sales were 40.');

        $execution = $this->launch();

        $this->assertSame('completed', $execution->status);
        $this->assertStringContainsString('Write it up.', $this->model->requests[1]->messages[0]['text']);
        $this->assertStringContainsString('Sales were 40.', $this->model->requests[1]->messages[0]['text']);
        $this->assertStringContainsString('<data source="step">', $this->model->requests[1]->messages[0]['text']);
        $this->assertSame('Report: sales were 40.', $execution->output_data['results'][0]['result']);
        $this->assertSame(2, AgentRun::where('workflow_execution_id', $execution->id)->count());
    }

    public function test_it_is_not_complete_because_the_first_agent_finished(): void
    {
        Queue::fake();
        $this->node('collect');
        $this->node('report', [], $this->second);
        $this->line('collect', 'report');

        $execution = $this->launch();

        $this->assertSame('running', $execution->status);
        $this->finishRuns('Done.');
        app(WorkflowConductor::class)->advance($execution->id);

        $this->assertSame('running', $execution->fresh()->status);
        $this->assertSame(WorkflowStep::RUNNING, $this->step('report')->status);
    }

    public function test_a_later_step_failing_fails_the_whole_workflow_and_says_where(): void
    {
        $this->node('collect');
        $this->node('report', [], $this->second);
        $this->line('collect', 'report');
        $this->model->say('Sales were 40.')->fail('invalid_request', 'The provider refused this request.');

        $execution = $this->launch();

        $this->assertSame('failed', $execution->status);
        $this->assertStringContainsString('Stopped at "report"', $execution->error_message);
    }

    public function test_parallel_steps_both_run_and_the_next_waits_for_both(): void
    {
        Queue::fake();
        $this->node('start');
        $this->node('left', [], $this->second);
        $this->node('right', [], $this->third);
        $this->node('join');
        $this->line('start', 'left');
        $this->line('start', 'right');
        $this->line('left', 'join');
        $this->line('right', 'join');
        $execution = $this->launch();

        $this->finishRuns('Start done.');
        app(WorkflowConductor::class)->advance($execution->id);

        $this->assertSame(WorkflowStep::RUNNING, $this->step('left')->status);
        $this->assertSame(WorkflowStep::RUNNING, $this->step('right')->status);

        $this->step('left')->run->update(['status' => AgentRun::COMPLETED, 'output' => 'Left done.']);
        app(WorkflowConductor::class)->advance($execution->id);
        $this->assertSame(WorkflowStep::PENDING, $this->step('join')->status);

        $this->step('right')->run->update(['status' => AgentRun::COMPLETED, 'output' => 'Right done.']);
        app(WorkflowConductor::class)->advance($execution->id);
        $this->assertSame(WorkflowStep::RUNNING, $this->step('join')->status);
        $this->assertStringContainsString('Left done.', $this->step('join')->input);
        $this->assertStringContainsString('Right done.', $this->step('join')->input);
    }

    public function test_a_branch_takes_only_the_line_whose_condition_holds(): void
    {
        $this->node('check');
        $this->node('fine', [], $this->second);
        $this->node('problem', [], $this->third);
        $this->line('check', 'fine', ['op' => 'not_contains', 'value' => 'mismatch']);
        $this->line('check', 'problem', ['op' => 'contains', 'value' => 'mismatch']);
        $this->model->say('There is a Mismatch on line 3.')->say('Raised with accounts.');

        $execution = $this->launch();

        $this->assertSame('completed', $execution->status);
        $this->assertSame(WorkflowStep::SKIPPED, $this->step('fine')->status);
        $this->assertSame(WorkflowStep::COMPLETED, $this->step('problem')->status);
    }

    public function test_a_failure_line_handles_the_failure(): void
    {
        $this->node('try');
        $this->node('next', [], $this->second);
        $this->node('recover', [], $this->third);
        $this->line('try', 'next');
        $this->line('try', 'recover', ['op' => 'failed']);
        $this->model->fail('invalid_request', 'No.')->say('Recovered.');

        $execution = $this->launch();

        $this->assertSame(WorkflowStep::COMPLETED, $this->step('recover')->status);
        $this->assertSame(WorkflowStep::SKIPPED, $this->step('next')->status);
        $this->assertStringContainsString('These steps failed', ucfirst(str_replace('Finished, but these', 'These', (string) $execution->error_message)));
        $this->assertSame('partial', $execution->status);
    }

    public function test_a_failure_line_is_not_taken_when_the_step_worked(): void
    {
        $this->node('try');
        $this->node('recover', [], $this->third);
        $this->line('try', 'recover', ['op' => 'failed']);
        $this->model->say('It worked.');

        $this->launch();

        $this->assertSame(WorkflowStep::SKIPPED, $this->step('recover')->status);
    }

    public function test_no_path_reaching_the_end_is_not_success(): void
    {
        $this->node('check');
        $this->node('a', [], $this->second);
        $this->node('b', [], $this->third);
        $this->line('check', 'a', ['op' => 'contains', 'value' => 'alpha']);
        $this->line('check', 'b', ['op' => 'contains', 'value' => 'beta']);
        $this->model->say('Neither.');

        $execution = $this->launch();

        $this->assertSame('failed', $execution->status);
        $this->assertStringContainsString('No path reached the end', $execution->error_message);
    }

    public function test_a_finished_run_brings_the_workflow_back(): void
    {
        Queue::fake();
        $this->node('one');
        $this->launch();
        $this->model->say('Done.');

        app(RunStepper::class)->advance(AgentRun::firstOrFail()->id);

        Queue::assertPushed(AdvanceWorkflow::class);
    }

    public function test_a_supervisor_cannot_reach_into_another_team(): void
    {
        $this->team();
        $other = AgentTeam::create(['organization_id' => $this->organization->id, 'name' => 'Other', 'supervisor_deployment_id' => $this->second->id]);
        AgentTeamMember::create(['organization_id' => $this->organization->id, 'agent_team_id' => $other->id, 'agent_deployment_id' => $this->third->id]);
        $this->model->call('delegate_task', ['teammate' => $this->third->id, 'task' => 'Check.'])->say('No.');

        $this->start($this->first);

        $this->assertSame('tool_failed', AgentEffect::firstOrFail()->error_code);
    }

    public function test_a_step_cannot_use_another_businesss_agent(): void
    {
        $theirs = TenantContext::system(fn () => AgentDeployment::factory()->create(['status' => 'active']));
        $this->node('a', [], $theirs);

        $execution = $this->launch();

        $this->assertSame('failed', $execution->status);
        $this->assertSame(0, AgentRun::withoutGlobalScopes()->where('agent_deployment_id', $theirs->id)->count());
    }

    public function test_a_step_set_to_carry_on_gives_finished_with_problems_not_success(): void
    {
        $this->node('optional', ['on_failure' => 'continue']);
        $this->node('main', [], $this->second);
        $this->model->fail('invalid_request', 'No.')->say('Done.');

        $execution = $this->launch();

        $this->assertSame('partial', $execution->status);
    }

    public function test_a_result_that_fails_its_check_is_a_failed_step(): void
    {
        $this->node('extract', ['expect' => ['format' => 'json', 'keys' => ['total']]]);
        $this->node('next', [], $this->second);
        $this->line('extract', 'next');
        $this->model->say('{"subtotal": 5}');

        $execution = $this->launch();

        $this->assertSame('failed', $execution->status);
        $this->assertStringContainsString('missing total', $execution->error_message);
    }

    public function test_a_step_can_retry_before_it_counts_as_failed(): void
    {
        $this->node('extract', ['expect' => ['contains' => 'total'], 'retries' => 1]);
        $this->model->say('Nothing useful.')->say('The total is 5.');

        $execution = $this->launch();

        $this->assertSame('completed', $execution->status);
        $this->assertSame(2, $this->step('extract')->attempts);
    }

    public function test_an_approval_step_holds_the_workflow_until_a_person_answers(): void
    {
        $this->node('draft');
        $this->node('check', ['kind' => 'approval']);
        $this->node('send', [], $this->second);
        $this->line('draft', 'check');
        $this->line('check', 'send');
        $this->model->say('Draft letter.')->say('Sent.');

        $execution = $this->launch();

        $this->assertSame('waiting_approval', $execution->status);
        $this->assertSame(WorkflowStep::PENDING, $this->step('send')->status);

        $approval = AgentApproval::firstOrFail();
        $this->assertSame('Draft letter.', $approval->proposed_action['values']['draft']);

        app(ProcessApprovalAction::class)->execute($approval, new ProcessApprovalData($approval->id, 'approved'));

        $this->assertSame('completed', $execution->fresh()->status);
    }

    public function test_rejecting_an_approval_step_stops_the_workflow_and_says_so(): void
    {
        $this->node('draft');
        $this->node('check', ['kind' => 'approval']);
        $this->node('send', [], $this->second);
        $this->line('draft', 'check');
        $this->line('check', 'send');
        $this->model->say('Draft letter.');
        $execution = $this->launch();
        $approval = AgentApproval::firstOrFail();

        app(ProcessApprovalAction::class)->execute($approval, new ProcessApprovalData($approval->id, 'rejected', 'Wrong tone.'));

        $execution->refresh();
        $this->assertSame('failed', $execution->status);
        $this->assertStringContainsString('Wrong tone.', $execution->error_message);
        $this->assertSame(WorkflowStep::SKIPPED, $this->step('send')->status);
    }

    public function test_a_step_waiting_on_its_own_tool_approval_shows_as_waiting(): void
    {
        DB::table('agent_config_versions')->where('id', $this->first->published_version_id)->update(['config' => encrypt(json_encode(['tools' => [['key' => 'calculator', 'mode' => 'approve']]]), false)]);
        $this->node('sum');
        $this->model->call('calculator', ['expression' => '1+1']);

        $execution = $this->launch();

        app(WorkflowConductor::class)->advance($execution->id);

        $this->assertSame('waiting_approval', $execution->fresh()->status);
    }

    public function test_a_tool_step_goes_through_every_gate_with_no_model(): void
    {
        DB::table('agent_config_versions')->where('id', $this->first->published_version_id)->update(['config' => encrypt(json_encode(['tools' => [['key' => 'calculator', 'mode' => 'auto']]]), false)]);
        $this->node('add', ['kind' => 'tool', 'tool' => 'calculator', 'arguments' => ['expression' => '20 + 22']]);

        $execution = $this->launch();

        $this->assertSame('completed', $execution->status);
        $this->assertStringContainsString('42', $execution->output_data['results'][0]['result']);
        $this->assertSame([], $this->model->requests);
    }

    public function test_a_tool_step_cannot_use_a_tool_the_agent_was_not_given(): void
    {
        $this->node('message', ['kind' => 'tool', 'tool' => 'notify_member', 'arguments' => ['user_id' => $this->owner->id, 'subject' => 'x', 'message' => 'y']]);

        $execution = $this->launch();

        $this->assertSame('failed', $execution->status);
        $this->assertSame('not_granted', AgentEffect::firstOrFail()->error_code);
        $this->assertSame(0, DB::table('notifications')->where('type', 'like', '%AgentMessage%')->count());
    }

    public function test_outside_content_stays_with_the_work_as_it_is_handed_on(): void
    {
        DB::table('agent_config_versions')->where('id', $this->first->published_version_id)->update(['config' => encrypt(json_encode(['tools' => [['key' => 'web_read', 'mode' => 'auto']]]), false)]);
        Http::fake(['*' => Http::response('Page text', 200)]);
        $this->app->instance(UrlGuard::class, new UrlGuard(fn () => ['93.184.216.34']));
        $this->node('read');
        $this->node('summarise', [], $this->second);
        $this->line('read', 'summarise');
        $this->model->call('web_read', ['url' => 'https://example.com'])->say('The page says hello.')->say('Summary.');

        $this->launch();

        $this->assertTrue(RunTaint::has($this->step('summarise')->run, RunTaint::UNTRUSTED));
    }

    public function test_what_was_published_is_what_runs_even_if_the_workflow_is_edited_during_it(): void
    {
        Queue::fake();
        $this->node('one');
        $this->node('two', ['instruction' => 'Original.'], $this->second);
        $this->line('one', 'two');
        $execution = $this->launch();

        $this->workflow->nodes()->where('uuid', 'two')->update(['config' => json_encode(['deployment_id' => $this->second->id, 'instruction' => 'Changed.'])]);
        $this->finishRuns('Done.');
        app(WorkflowConductor::class)->advance($execution->id);

        $this->assertStringContainsString('Original.', $this->step('two')->input);
    }

    public function test_the_same_event_twice_starts_one_execution(): void
    {
        Queue::fake();
        $this->node('one');
        $conductor = app(WorkflowConductor::class);

        $first = $conductor->start($this->workflow, 'Go', $this->owner->id, idempotencyKey: 'event-1');
        $second = $conductor->start($this->workflow, 'Go', $this->owner->id, idempotencyKey: 'event-1');

        $this->assertTrue($first->is($second));
        $this->assertSame(1, WorkflowExecution::count());
    }

    public function test_a_person_can_cancel_and_what_is_running_stops(): void
    {
        Queue::fake();
        $this->node('one');
        $this->node('two', [], $this->second);
        $this->line('one', 'two');
        $execution = $this->launch();
        $run = AgentRun::firstOrFail();

        app(WorkflowConductor::class)->cancel($execution);

        $this->assertSame('cancelled', $execution->fresh()->status);
        $this->assertNotNull($run->fresh()->cancel_requested_at);
        $this->assertSame(WorkflowStep::CANCELLED, $this->step('two')->status);
    }

    public function test_a_failed_workflow_resumes_from_the_failed_step_without_redoing_the_rest(): void
    {
        $this->node('one');
        $this->node('two', [], $this->second);
        $this->node('three', [], $this->third);
        $this->line('one', 'two');
        $this->line('two', 'three');
        $this->model->say('One done.')->fail('invalid_request', 'No.');
        $execution = $this->launch();
        $this->assertSame('failed', $execution->status);

        $this->model->say('Two done.')->say('Three done.');
        app(WorkflowConductor::class)->retry($execution, $this->step('two'));

        $this->assertSame('completed', $execution->fresh()->status);
        $this->assertSame(1, AgentRun::where('agent_deployment_id', $this->first->id)->count());
    }

    public function test_an_interrupted_workflow_is_picked_up(): void
    {
        Queue::fake();
        $this->node('one');
        $execution = $this->launch();
        $this->finishRuns('Done.');
        WorkflowExecution::whereKey($execution->id)->update(['updated_at' => now()->subHour()]);
        Queue::fake();

        app(RunSweeper::class)->sweep();

        Queue::assertPushed(AdvanceWorkflow::class);
    }

    public function test_a_paused_agent_mid_workflow_fails_the_step_truthfully(): void
    {
        $this->second->update(['status' => 'paused']);
        $this->node('one');
        $this->node('two', [], $this->second);
        $this->line('one', 'two');
        $this->model->say('One done.');

        $execution = $this->launch();

        $this->assertSame('failed', $execution->status);
        $this->assertStringContainsString('not active', $execution->error_message);
    }

    public function test_the_cost_is_the_sum_of_its_runs(): void
    {
        ModelPrice::create(['provider' => 'scripted', 'model' => 'scripted', 'input_per_million' => 1000, 'output_per_million' => 1000, 'effective_from' => now()->subDay(), 'source' => 'test']);
        $this->node('one');
        $this->node('two', [], $this->second);
        $this->line('one', 'two');
        $this->model->say('One.')->say('Two.');

        $execution = $this->launch();

        $this->assertEqualsWithDelta(0.24, (float) $execution->total_cost, 0.0001);
    }

    private function team(): AgentTeam
    {
        $team = AgentTeam::create(['organization_id' => $this->organization->id, 'name' => 'Reporting', 'supervisor_deployment_id' => $this->first->id]);
        AgentTeamMember::create(['organization_id' => $this->organization->id, 'agent_team_id' => $team->id, 'agent_deployment_id' => $this->second->id, 'responsibility' => 'Checks the figures']);

        return $team;
    }

    private function start(AgentDeployment $agent, string $input = 'Do the report', string $trigger = 'manual'): AgentRun
    {
        return app(StartRunAction::class)->execute($agent->fresh(), new StartRunData($input, $trigger))->refresh();
    }

    public function test_a_supervisor_hands_work_to_a_teammate_waits_and_uses_the_result(): void
    {
        $this->team();
        $this->model->call('delegate_task', ['teammate' => $this->second->id, 'task' => 'Check the figures.'])
            ->say('The figures are right.')
            ->say('Report done, figures checked.');

        $run = $this->start($this->first);

        $this->assertSame(AgentRun::COMPLETED, $run->refresh()->status);
        $this->assertSame('Report done, figures checked.', $run->output);
        $this->assertSame(AgentRun::COMPLETED, AgentRun::where('parent_run_id', $run->id)->firstOrFail()->status);
        $this->assertStringContainsString('Checks the figures', $this->model->requests[0]->system);
        $this->assertStringContainsString('The figures are right.', json_encode($this->model->requests[2]->messages));
    }

    public function test_a_supervisor_hears_when_a_teammate_fails(): void
    {
        $this->team();
        $this->model->call('delegate_task', ['teammate' => $this->second->id, 'task' => 'Check.'])
            ->fail('invalid_request', 'No.')
            ->say('My teammate could not do it.');

        $run = $this->start($this->first);

        $this->assertStringContainsString('could not finish', json_encode($this->model->requests[2]->messages));
        $this->assertSame(AgentRun::COMPLETED, $run->refresh()->status);
    }

    public function test_only_a_teammate_can_be_given_work_and_only_a_supervisor_can_give_it(): void
    {
        $this->team();
        $this->model->call('delegate_task', ['teammate' => $this->third->id, 'task' => 'Check.'])->say('No.')->say('Fine.');

        $this->start($this->first);
        $this->assertSame('tool_failed', AgentEffect::firstOrFail()->error_code);

        $this->start($this->second);
        $this->assertSame([], end($this->model->requests)->tools);
    }

    public function test_handing_work_on_launders_nothing(): void
    {
        $this->team();
        $this->model->call('delegate_task', ['teammate' => $this->second->id, 'task' => 'Check.'])->say('Fine.')->say('Done.');

        $run = $this->start($this->first, 'Hi', 'webhook');

        $this->assertTrue(RunTaint::has(AgentRun::where('parent_run_id', $run->id)->firstOrFail(), RunTaint::UNTRUSTED));
    }

    public function test_what_a_teammate_was_exposed_to_comes_back_to_the_supervisor(): void
    {
        $this->team();
        Queue::fake();
        $run = $this->start($this->first);
        $child = AgentRun::create(['organization_id' => $this->organization->id, 'agent_deployment_id' => $this->second->id, 'config_version_id' => $this->second->published_version_id, 'parent_run_id' => $run->id, 'trigger_type' => 'delegation', 'status' => AgentRun::COMPLETED, 'output' => 'x', 'input' => 'y', 'limits' => [], 'taint' => RunTaint::UNTRUSTED]);
        AgentEffect::create(['organization_id' => $this->organization->id, 'agent_run_id' => $run->id, 'call_id' => 'a', 'tool_key' => 'delegate_task', 'idempotency_key' => 'k', 'status' => AgentEffect::WAITING, 'arguments' => [], 'child_run_id' => $child->id]);

        app(DelegationReturn::class)->back($child);

        $this->assertTrue(RunTaint::has($run->refresh(), RunTaint::UNTRUSTED));
    }

    public function test_a_chain_cannot_loop_or_go_too_deep(): void
    {
        $this->team();
        $back = AgentTeam::create(['organization_id' => $this->organization->id, 'name' => 'Back', 'supervisor_deployment_id' => $this->second->id]);
        AgentTeamMember::create(['organization_id' => $this->organization->id, 'agent_team_id' => $back->id, 'agent_deployment_id' => $this->first->id]);
        $this->model->call('delegate_task', ['teammate' => $this->second->id, 'task' => 'Check.'])
            ->call('delegate_task', ['teammate' => $this->first->id, 'task' => 'Check back.'])
            ->say('I cannot.')->say('Done.');

        $this->start($this->first);

        $this->assertStringContainsString('already part of this chain', AgentEffect::where('agent_run_id', AgentRun::whereNotNull('parent_run_id')->value('id'))->firstOrFail()->error_message);
    }

    public function test_a_waiting_supervisor_holds_no_worker_and_two_teammates_can_work_at_once(): void
    {
        Queue::fake();
        $team = $this->team();
        AgentTeamMember::create(['organization_id' => $this->organization->id, 'agent_team_id' => $team->id, 'agent_deployment_id' => $this->third->id]);
        $this->model->callMany([
            ['id' => 'a', 'name' => 'delegate_task', 'arguments' => ['teammate' => $this->second->id, 'task' => 'One.']],
            ['id' => 'b', 'name' => 'delegate_task', 'arguments' => ['teammate' => $this->third->id, 'task' => 'Two.']],
        ]);
        $run = $this->start($this->first);

        foreach (range(1, 4) as $i) {
            app(RunStepper::class)->advance($run->id);
        }

        $this->assertSame(AgentRun::WAITING_AGENT, $run->refresh()->status);
        $this->assertSame(2, AgentRun::where('parent_run_id', $run->id)->count());
        $this->assertSame(RunStepper::IDLE, app(RunStepper::class)->advance($run->id));
    }

    public function test_a_workflow_that_cannot_work_is_refused_before_it_runs(): void
    {
        $this->assertSame(['Add at least one step.'], app(WorkflowValidator::class)->problems($this->workflow));

        $this->workflow->nodes()->create(['uuid' => 'a', 'organization_id' => $this->organization->id, 'agent_key' => 'agent', 'label' => 'a', 'position_x' => 0, 'position_y' => 0, 'config' => ['kind' => 'agent']]);
        $this->assertSame(['Choose an agent for "a".'], app(WorkflowValidator::class)->problems($this->workflow));

        $this->expectException(ValidationException::class);
        app(StartWorkflowAction::class)->execute($this->workflow, new StartWorkflowData('Go', $this->owner));
    }

    public function test_the_validator_finds_loops_paused_agents_and_empty_conditions(): void
    {
        $this->second->update(['status' => 'paused']);
        $this->node('a');
        $this->node('b', [], $this->second);
        $this->node('c', ['kind' => 'approval']);
        $this->node('d', ['kind' => 'tool', 'tool' => '']);
        $this->line('a', 'b', ['op' => 'contains', 'value' => '']);
        $this->line('b', 'a');
        $this->line('b', 'c');

        $problems = implode(' ', app(WorkflowValidator::class)->problems($this->workflow));

        $this->assertStringContainsString('loop back', $problems);
        $this->assertStringContainsString('not active', $problems);
        $this->assertStringContainsString('nothing to compare', $problems);
        $this->assertStringContainsString('Choose a tool', $problems);
    }

    public function test_an_approval_with_nothing_before_it_is_refused(): void
    {
        $this->node('c', ['kind' => 'approval']);

        $this->assertStringContainsString('needs a step before it', app(WorkflowValidator::class)->problems($this->workflow)[0]);
    }

    public function test_someone_who_cannot_operate_agents_cannot_run_or_control_a_workflow(): void
    {
        Queue::fake();
        $this->node('a');
        $execution = $this->launch();
        $viewer = User::factory()->create();
        $this->organization->users()->attach($viewer, ['role' => 'viewer']);

        try {
            app(StartWorkflowAction::class)->execute($this->workflow, new StartWorkflowData('Go', $viewer));
            $this->fail('A viewer ran a workflow.');
        } catch (AuthorizationException) {
            $this->expectException(AuthorizationException::class);
            app(ControlWorkflowExecutionAction::class)->cancel($execution, new ControlExecutionData($viewer));
        }
    }

    public function test_the_diagram_saves_and_runs_from_the_page(): void
    {
        $this->model->say('Done.');
        $node = ['id' => 'step-a', 'agent_key' => 'agent', 'label' => 'Do it', 'x' => 10, 'y' => 20, 'config' => ['kind' => 'agent', 'deployment_id' => $this->first->id, 'instruction' => 'Do it.']];

        Livewire::test(WorkflowBuilder::class, ['workflowId' => $this->workflow->id])
            ->set('nodes', [$node])
            ->call('runWith', 'Please do it')
            ->assertDispatched('execution-started');

        $this->assertSame('completed', WorkflowExecution::firstOrFail()->status);
        $this->assertSame('Please do it', WorkflowExecution::firstOrFail()->input_data['text']);
    }

    public function test_the_page_will_not_publish_a_workflow_with_a_problem(): void
    {
        $node = ['id' => 'step-a', 'agent_key' => 'agent', 'label' => 'Do it', 'x' => 10, 'y' => 20, 'config' => ['kind' => 'agent']];

        Livewire::test(WorkflowBuilder::class, ['workflowId' => $this->workflow->id])->set('nodes', [$node])->call('publish')->assertSet('flashType', 'warning');

        $this->assertNotSame('active', $this->workflow->fresh()->status);
    }

    public function test_runs_can_be_inspected_with_what_went_in_and_came_out(): void
    {
        $this->node('one', ['instruction' => 'Count them.']);
        $this->model->say('There are four.');
        $execution = $this->launch();

        Livewire::test(WorkflowExecutions::class, ['workflowId' => $this->workflow->id])
            ->call('open', $execution->id)
            ->call('openStep', $this->step('one')->id)
            ->assertSee('Count them.')
            ->assertSee('There are four.');
    }

    public function test_a_failed_step_can_be_retried_from_the_page(): void
    {
        $this->node('one');
        $this->model->fail('invalid_request', 'No.')->say('Done.');
        $execution = $this->launch();

        Livewire::test(WorkflowExecutions::class, ['workflowId' => $this->workflow->id])->call('open', $execution->id)->call('retry', $this->step('one')->id);

        $this->assertSame('completed', $execution->fresh()->status);
    }

    public function test_a_template_gives_a_workflow_that_only_needs_its_agents_chosen(): void
    {
        Livewire::test(WorkflowList::class)->call('useTemplate', 'report-with-review');

        $workflow = AgentWorkflow::latest('id')->firstOrFail();

        $this->assertSame(4, $workflow->nodes()->count());
        $this->assertSame(3, $workflow->connections()->count());
        $this->assertStringContainsString('Choose an agent', app(WorkflowValidator::class)->problems($workflow)[0]);
    }

    public function test_every_template_has_a_sound_shape(): void
    {
        foreach (array_keys(config('workflow_templates')) as $key) {
            Livewire::test(WorkflowList::class)->call('useTemplate', $key);
            $graph = WorkflowGraph::of(AgentWorkflow::latest('id')->firstOrFail());

            $this->assertCount(count($graph->nodes), $graph->ordered(), $key);
        }
    }

    public function test_a_team_can_be_made_from_the_page(): void
    {
        Livewire::test(AgentTeams::class)
            ->set('name', 'Reporting')
            ->set('supervisorId', $this->first->id)
            ->set("members.{$this->second->id}", ['on' => true, 'responsibility' => 'Checks figures'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Reporting');

        $this->assertSame('Checks figures', AgentTeamMember::firstOrFail()->responsibility);
    }

    public function test_a_team_cannot_hold_another_businesss_agent_or_lead_itself(): void
    {
        $theirs = TenantContext::system(fn () => AgentDeployment::factory()->create());
        $save = fn (array $members) => app(SaveAgentTeamAction::class)->execute(new SaveAgentTeamData($this->owner, $this->organization->id, 'T', null, $this->first->id, $members));

        foreach ([[$theirs->id => 'x'], [$this->first->id => 'x'], []] as $members) {
            try {
                $save($members);
                $this->fail('Saved a bad team.');
            } catch (ValidationException) {
                $this->assertSame(0, AgentTeam::count());
            }
        }
    }

    public function test_the_kill_switch_stops_the_runs_under_a_workflow(): void
    {
        Queue::fake();
        $this->node('a');
        $execution = $this->launch();

        app(EmergencyKillSwitchAction::class)->killAllWorkflows($this->organization);

        $this->assertSame('aborted', $execution->fresh()->status);
        $this->assertNotNull(AgentRun::firstOrFail()->cancel_requested_at);
    }

    public function test_the_workflow_pages_render(): void
    {
        $this->owner->update(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);

        $this->get(route('workflows.index'))->assertOk()->assertSee('Start from a template');
        $this->get(route('workflows.builder', $this->workflow))->assertOk()->assertSee('Add agent');
        $this->get(route('agents.teams'))->assertOk()->assertSee('New team');
    }
}
