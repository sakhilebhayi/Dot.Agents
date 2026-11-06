<?php

namespace Tests\Feature\Agents;

use App\Actions\Agents\ChangeAgentStageAction;
use App\Actions\Agents\RunAgentTestsAction;
use App\Actions\Agents\SaveTestCaseAction;
use App\Actions\Agents\StartRunAction;
use App\DTOs\Agents\ChangeAgentStageData;
use App\DTOs\Agents\RunTestsData;
use App\DTOs\Agents\SaveTestCaseData;
use App\DTOs\Agents\StartRunData;
use App\Exceptions\RunRefused;
use App\Jobs\FireBusinessEvent;
use App\Livewire\Agents\AgentTestBench;
use App\Models\AgentDeployment;
use App\Models\AgentEffect;
use App\Models\AgentMemory;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Models\AgentTestCase;
use App\Models\AgentTestResult;
use App\Models\AgentTrigger;
use App\Models\AgentWorkflow;
use App\Models\Organization;
use App\Models\User;
use App\Services\Agents\AgentConfig;
use App\Services\Agents\AgentConfigReview;
use App\Services\Models\ScriptedGateway;
use App\Services\Runs\RunStepper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TestBenchTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    private AgentDeployment $agent;

    private ScriptedGateway $model;

    protected function setUp(): void
    {
        parent::setUp();

        config(['prism.default_provider' => 'scripted']);
        $this->model = app(ScriptedGateway::class);
        $this->owner = User::factory()->create(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id]);
        $this->organization->users()->attach($this->owner, ['role' => 'owner']);
        session(['current_organization_id' => $this->organization->id]);
        $this->actingAs($this->owner);
        $this->agent = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'draft', 'enable_memory' => false, 'autonomy_level' => 1, 'responsibility' => 'Check supplier invoices against our terms.', 'owner_id' => $this->owner->id]);
    }

    /** @param  array<string, mixed>  $settings */
    private function configure(array $settings): void
    {
        DB::table('agent_config_versions')->where('id', $this->agent->published_version_id)->update(['config' => encrypt(json_encode($settings), false)]);
    }

    private function test(string $input = 'Try this', array $data = []): AgentRun
    {
        return app(StartRunAction::class)->execute($this->agent->fresh(), new StartRunData(...['input' => $input, 'isTest' => true, ...$data]))->refresh();
    }

    private function test_case(array $checks = []): AgentTestCase
    {
        return app(SaveTestCaseAction::class)->execute($this->agent, new SaveTestCaseData($this->owner, 'Leave question', 'How much leave?', 'manual', $checks));
    }

    public function test_a_draft_agent_can_be_tested_but_not_run_for_real(): void
    {
        $this->model->say('Fine.');

        $this->assertSame(AgentRun::COMPLETED, $this->test()->status);
    }

    public function test_a_test_changes_nothing_and_reaches_nothing_outside(): void
    {
        Http::fake();
        $this->agent->update(['autonomy_level' => 4]);
        $this->configure(['tools' => [['key' => 'notify_member', 'mode' => 'auto'], ['key' => 'web_read', 'mode' => 'auto']]]);
        $this->model->call('notify_member', ['user_id' => $this->owner->id, 'subject' => 'x', 'message' => 'y'])
            ->call('web_read', ['url' => 'https://example.com/?secret=1'])->say('Done.');

        $run = $this->test();

        $this->assertSame(0, DB::table('notifications')->where('type', 'like', '%AgentMessage%')->count());
        Http::assertNothingSent();
        $this->assertSame(AgentRun::COMPLETED, $run->status);
        $this->assertStringContainsString('[Test run] Nothing was done', json_encode($this->model->requests[1]->messages));
    }

    public function test_a_test_does_not_wait_for_a_person_but_says_it_would_have_asked(): void
    {
        $this->configure(['tools' => [['key' => 'calculator', 'mode' => 'approve']]]);
        $this->model->call('calculator', ['expression' => '1+1'])->say('Two.');

        $run = $this->test();

        $this->assertSame(AgentRun::COMPLETED, $run->status);
        $this->assertSame('A person would be asked here', $run->steps()->where('kind', AgentRunStep::APPROVAL)->first()->title);
    }

    public function test_a_test_still_refuses_what_the_agent_may_not_do(): void
    {
        $this->model->call('notify_member', ['user_id' => $this->owner->id, 'subject' => 'x', 'message' => 'y'])->say('No.');

        $this->test();

        $this->assertSame('not_granted', AgentEffect::firstOrFail()->error_code);
    }

    public function test_a_test_saves_no_memories(): void
    {
        $this->agent->update(['autonomy_level' => 4]);
        $this->configure(['memory' => ['long_term' => true]]);
        $this->model->call('remember_fact', ['subject' => 'A', 'fact' => 'B', 'about' => 'this_agent'])->say('Done.');

        $this->test();

        $this->assertSame(0, AgentMemory::count());
    }

    public function test_a_tool_can_be_made_to_fail_on_purpose(): void
    {
        $this->configure(['tools' => [['key' => 'calculator', 'mode' => 'auto']]]);
        $this->model->call('calculator', ['expression' => '1+1'])->say('The tool failed, so I stopped.');

        $this->test(data: ['failTool' => 'calculator']);

        $this->assertSame('tool_failed', AgentEffect::firstOrFail()->error_code);
    }

    public function test_only_a_test_can_make_a_tool_fail(): void
    {
        $this->agent->update(['status' => 'active']);
        $this->configure(['tools' => [['key' => 'calculator', 'mode' => 'auto']]]);
        $this->model->call('calculator', ['expression' => '1+1'])->say('Two.');

        app(StartRunAction::class)->execute($this->agent->fresh(), new StartRunData('Go', failTool: 'calculator'));

        $this->assertSame(AgentEffect::DONE, AgentEffect::firstOrFail()->status);
    }

    public function test_outside_content_can_be_simulated(): void
    {
        Queue::fake();

        $this->assertSame(1, $this->test(data: ['triggerType' => 'webhook'])->taint);
    }

    public function test_a_failed_test_does_not_set_off_business_events(): void
    {
        Queue::fake();
        AgentTrigger::create(['organization_id' => $this->organization->id, 'target_type' => 'agent', 'target_id' => $this->agent->id, 'kind' => 'event', 'name' => 'x', 'settings' => ['event' => 'run.failed']]);
        Queue::fake();
        $run = $this->test();
        $this->model->fail('invalid_request', 'No.');

        app(RunStepper::class)->advance($run->id);

        Queue::assertNotPushed(FireBusinessEvent::class);
    }

    public function test_two_versions_can_be_compared_side_by_side(): void
    {
        $newer = $this->agent->versions()->create(['organization_id' => $this->organization->id, 'number' => 2, 'state' => 'draft', 'config' => AgentConfig::normalise(['instructions' => 'Newer.'])]);
        $this->model->say('New answer.')->say('Old answer.');

        Livewire::test(AgentTestBench::class, ['deploymentId' => $this->agent->id])
            ->set('input', 'Compare these')
            ->set('versionId', $newer->id)
            ->set('compareId', $this->agent->published_version_id)
            ->call('start')
            ->assertSee('New answer.')
            ->assertSee('Old answer.')
            ->assertSee('Version 2')
            ->assertSee('Version 1');

        $this->assertSame(2, AgentRun::where('is_test', true)->count());
    }

    public function test_the_page_shows_each_step_with_its_inputs_and_outputs(): void
    {
        $this->configure(['tools' => [['key' => 'calculator', 'mode' => 'auto']]]);
        $this->model->call('calculator', ['expression' => '40 + 2'])->say('It is 42.');

        Livewire::test(AgentTestBench::class, ['deploymentId' => $this->agent->id])
            ->set('input', 'Add it up')
            ->call('start')
            ->assertSee('Asked for calculator')
            ->assertSee('40 + 2')
            ->assertSee('What came back')
            ->assertSee('It is 42.');
    }

    public function test_a_saved_test_is_marked_on_facts(): void
    {
        $this->configure(['tools' => [['key' => 'calculator', 'mode' => 'auto']]]);
        $case = $this->testCase(['contains' => ['21 days'], 'not_contains' => ['maybe'], 'uses_tools' => ['calculator'], 'avoids_tools' => ['web_read']]);
        $this->model->call('calculator', ['expression' => '7*3'])->say('You get 21 days.');

        app(RunAgentTestsAction::class)->execute($this->agent, new RunTestsData($this->owner, $this->agent->published_version_id));

        $result = AgentTestResult::firstOrFail();
        $this->assertSame('passed', $result->status, json_encode($result->checks));
        $this->assertSame($case->id, $result->agent_test_case_id);
        $this->assertSame(1, $result->measures['tools']);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function failures(): array
    {
        return [
            'missing words' => [['contains' => ['21 days']], 'It says "21 days"'],
            'forbidden words' => [['not_contains' => ['fine']], 'It does not say "fine"'],
            'tool not used' => [['uses_tools' => ['calculator']], 'It used calculator'],
            'source not used' => [['cites' => 'Leave policy'], 'It used the document Leave policy'],
        ];
    }

    #[DataProvider('failures')]
    public function test_a_saved_test_fails_for_the_right_reason(array $checks, string $failed): void
    {
        $this->testCase($checks);
        $this->model->say('All fine.');

        app(RunAgentTestsAction::class)->execute($this->agent, new RunTestsData($this->owner, $this->agent->published_version_id));

        $result = AgentTestResult::firstOrFail();
        $this->assertSame('failed', $result->status);
        $this->assertContains($failed, array_column(array_filter($result->checks, fn ($check) => ! $check['passed']), 'name'));
    }

    public function test_an_unfinished_run_fails_its_test(): void
    {
        $this->testCase();
        $this->model->fail('invalid_request', 'No.');

        app(RunAgentTestsAction::class)->execute($this->agent, new RunTestsData($this->owner, $this->agent->published_version_id));

        $this->assertSame('failed', AgentTestResult::firstOrFail()->status);
    }

    public function test_breaking_a_permission_fails_a_test_even_with_a_good_answer(): void
    {
        $this->testCase();
        $this->model->call('notify_member', ['user_id' => $this->owner->id, 'subject' => 'x', 'message' => 'y'])->say('Good answer.');

        app(RunAgentTestsAction::class)->execute($this->agent, new RunTestsData($this->owner, $this->agent->published_version_id));

        $this->assertSame('failed', AgentTestResult::firstOrFail()->status);
    }

    public function test_a_failing_test_keeps_a_version_from_being_called_ready(): void
    {
        config(['prism.providers.anthropic.api_key' => 'test-only']);
        $version = $this->agent->publishedVersion;
        $this->configure(['instructions' => 'Check each line of the invoice, then list what does not match.']);
        $this->testCase(['contains' => ['21 days']]);
        $this->model->say('Wrong.');
        app(RunAgentTestsAction::class)->execute($this->agent, new RunTestsData($this->owner, $version->id));

        $review = app(AgentConfigReview::class)->review($this->agent, $version->fresh()->settings(), $version);

        $this->assertNotSame('ready', $review['verdict']);
        $this->assertContains('1 saved test failed on this version', array_column($review['findings'], 'title'));
    }

    public function test_the_review_warns_when_live_workflows_use_the_agent(): void
    {
        $workflow = AgentWorkflow::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active', 'created_by' => $this->owner->id]);
        $workflow->nodes()->create(['uuid' => 'a', 'organization_id' => $this->organization->id, 'agent_key' => 'agent', 'label' => 'a', 'position_x' => 0, 'position_y' => 0, 'config' => ['deployment_id' => $this->agent->id]]);
        $draft = $this->agent->versions()->create(['organization_id' => $this->organization->id, 'number' => 2, 'state' => 'draft', 'config' => AgentConfig::normalise(['instructions' => 'Newer instructions for this agent to follow.'])]);

        $review = app(AgentConfigReview::class)->review($this->agent, $draft->settings(), $draft);

        $this->assertContains('Live workflows use this agent', array_column($review['findings'], 'title'));
    }

    public function test_cases_can_be_saved_and_run_from_the_page(): void
    {
        $this->model->say('You get 21 days.');

        Livewire::test(AgentTestBench::class, ['deploymentId' => $this->agent->id])
            ->set('caseName', 'Leave')->set('caseInput', 'How much leave?')->set('mustSay', '21 days, leave')
            ->call('saveCase')->assertHasNoErrors()
            ->call('runCases')
            ->assertSee('Leave');

        $this->assertSame(['21 days', 'leave'], AgentTestCase::firstOrFail()->checks['contains']);
    }

    public function test_someone_who_cannot_change_the_agent_cannot_use_the_bench(): void
    {
        $viewer = User::factory()->create(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $this->organization->users()->attach($viewer, ['role' => 'viewer']);

        $this->actingAs($viewer)->get(route('agents.test', $this->agent))->assertForbidden();
    }

    public function test_an_agent_moves_through_its_stages_and_neither_takes_real_work(): void
    {
        $change = fn (string $stage) => app(ChangeAgentStageAction::class)->execute($this->agent->fresh(), new ChangeAgentStageData($this->owner, $stage));

        $change('testing');
        $change('staging');
        $this->assertSame('staging', $this->agent->fresh()->status);

        $this->expectException(RunRefused::class);
        app(StartRunAction::class)->execute($this->agent->fresh(), new StartRunData('Real work'));
    }

    public function test_a_stage_cannot_be_skipped(): void
    {
        $this->expectException(ValidationException::class);
        app(ChangeAgentStageAction::class)->execute($this->agent, new ChangeAgentStageData($this->owner, 'staging'));
    }

    public function test_the_page_renders(): void
    {
        $this->get(route('agents.test', $this->agent))->assertOk()->assertSee('Saved tests');
    }
}
