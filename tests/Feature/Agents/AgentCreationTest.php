<?php

namespace Tests\Feature\Agents;

use App\Actions\Agents\StartRunAction;
use App\DTOs\Agents\StartRunData;
use App\Jobs\ReportRunToDotMemory;
use App\Livewire\Agents\AgentCreate;
use App\Livewire\Agents\AgentSetup;
use App\Livewire\Agents\AgentVersions;
use App\Livewire\Agents\DeploymentOverview;
use App\Livewire\Marketplace\TemplateLibrary;
use App\Models\AgentConfigVersion;
use App\Models\AgentDeployment;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\BusinessTemplate;
use App\Models\KnowledgeBase;
use App\Models\Organization;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\Agents\AgentConfig;
use App\Services\Agents\AgentConfigReview;
use App\Services\Agents\AgentDescriptionReader;
use App\Services\Agents\AgentPackage;
use App\Services\Models\ScriptedGateway;
use App\Services\Runs\RunFinisher;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgentCreationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id, 'plan' => 'enterprise']);
        $this->organization->users()->attach($this->owner, ['role' => 'owner']);
        session(['current_organization_id' => $this->organization->id]);
        $this->actingAs($this->owner);
    }

    private function agent(array $attributes = []): AgentDeployment
    {
        return AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'draft', 'responsibility' => 'Check supplier invoices against our payment terms.', 'owner_id' => $this->owner->id, ...$attributes]);
    }

    /** @param  array<string, mixed>  $config */
    private function review(array $config, ?AgentDeployment $agent = null): array
    {
        $full = AgentConfig::normalise(['instructions' => 'Check each line of the invoice, then list what does not match.', ...$config]);

        return app(AgentConfigReview::class)->review($agent ?? $this->agent(), $full);
    }

    private function titles(array $review): array
    {
        return array_column($review['findings'], 'title');
    }

    public function test_a_description_becomes_a_proposal_without_creating_anything(): void
    {
        Livewire::test(AgentCreate::class)
            ->set('description', 'Check incoming supplier invoices, add up the totals and notify accounts when something does not match.')
            ->call('draft')
            ->assertSee('Drafted from your words')
            ->assertSee('Check it closely');

        $this->assertSame(0, AgentDeployment::count());
    }

    public function test_accepting_a_proposal_makes_a_draft_where_every_suggested_tool_asks_first(): void
    {
        $component = Livewire::test(AgentCreate::class)
            ->set('description', 'Check incoming supplier invoices, add up the totals and notify accounts when something does not match.')
            ->call('draft')
            ->call('acceptProposal');

        $agent = AgentDeployment::firstOrFail();
        $component->assertRedirect(route('agents.setup', $agent));

        $this->assertSame('draft', $agent->status);
        $config = $agent->draftVersion()->first()->settings();
        $this->assertContains('notify_member', array_column($config['tools'], 'key'));
        $this->assertSame(['approve'], array_values(array_unique(array_column($config['tools'], 'mode'))));
    }

    public function test_a_model_can_draft_but_cannot_pick_tools_that_do_not_exist_or_full_autonomy(): void
    {
        config(['prism.default_provider' => 'scripted']);
        app(ScriptedGateway::class)->say('Here you go: '.json_encode(['name' => 'Invoice Agent', 'role' => 'Accounts', 'responsibility' => 'Checks invoices.', 'purpose' => 'Accuracy.', 'instructions' => 'Check each line.', 'tools' => ['calculator', 'wire_money'], 'autonomy' => 4]));

        $proposal = app(AgentDescriptionReader::class)->read($this->organization->id, 'Check invoices for us.');

        $this->assertSame('model', $proposal['by']);
        $this->assertSame(['calculator'], $proposal['tools']);
        $this->assertSame(3, $proposal['autonomy']);
        $this->assertSame(120, (int) UsageRecord::where('organization_id', $this->organization->id)->sum('quantity'));
    }

    public function test_a_model_reply_that_cannot_be_read_falls_back_to_rules(): void
    {
        config(['prism.default_provider' => 'scripted']);
        app(ScriptedGateway::class)->say('I cannot do that.');

        $this->assertSame('rules', app(AgentDescriptionReader::class)->read($this->organization->id, 'Check invoices for us.')['by']);
    }

    public function test_a_model_failure_falls_back_to_rules(): void
    {
        config(['prism.default_provider' => 'scripted']);
        app(ScriptedGateway::class)->fail('overloaded');

        $this->assertSame('rules', app(AgentDescriptionReader::class)->read($this->organization->id, 'Check invoices for us.')['by']);
    }

    /** @return array<string, array{string}> */
    public static function templates(): array
    {
        return collect(require dirname(__DIR__, 3).'/config/agent_templates.php')->keys()->mapWithKeys(fn ($key) => [$key => [$key]])->all();
    }

    #[DataProvider('templates')]
    public function test_a_template_gives_a_draft_with_real_tools_and_nothing_blocking(string $key): void
    {
        Livewire::test(AgentCreate::class)->call('useTemplate', $key);

        $agent = AgentDeployment::firstOrFail();
        $review = app(AgentConfigReview::class)->review($agent, $agent->draftVersion()->first()->settings());

        $this->assertNotContains('blocker', array_column($review['findings'], 'level'), json_encode($review['findings']));
        $this->assertNotSame('ready', $review['verdict']);
    }

    public function test_an_agent_can_be_built_by_hand(): void
    {
        Livewire::test(AgentCreate::class)->set('name', 'Stock Clerk')->set('role', 'Warehouse')->call('build');

        $this->assertSame('Stock Clerk', AgentDeployment::firstOrFail()->name);
    }

    public function test_an_export_can_be_imported_and_knowledge_does_not_travel(): void
    {
        $agent = $this->agent(['name' => 'Original']);
        $agent->versions()->create(['organization_id' => $agent->organization_id, 'number' => 2, 'state' => 'draft', 'config' => AgentConfig::normalise(['instructions' => 'Do the work.', 'knowledge' => [5]])]);
        $file = UploadedFile::fake()->createWithContent('agent.json', json_encode(AgentPackage::export($agent)));

        Livewire::test(AgentCreate::class)->set('way', 'import')->set('package', $file)->call('import')->assertHasNoErrors();

        $copy = AgentDeployment::where('name', 'Original')->latest('id')->first();
        $this->assertNotSame($agent->id, $copy->id);
        $this->assertSame([], $copy->draftVersion()->first()->settings()['knowledge']);
    }

    public function test_a_file_that_is_not_an_export_is_refused(): void
    {
        Livewire::test(AgentCreate::class)->set('package', UploadedFile::fake()->createWithContent('x.json', '{"a":1}'))->call('import')->assertHasErrors('package');

        $this->assertSame(0, AgentDeployment::count());
    }

    public function test_an_agent_can_be_duplicated_as_a_draft(): void
    {
        $agent = $this->agent(['name' => 'Original', 'status' => 'active']);

        Livewire::test(DeploymentOverview::class, ['deploymentId' => $agent->id])->call('duplicate');

        $this->assertSame('draft', AgentDeployment::where('name', 'Original copy')->firstOrFail()->status);
    }

    public function test_an_agent_can_be_exported_from_its_page(): void
    {
        $response = $this->get(route('agents.export', $this->agent(['name' => 'Original'])));

        $response->assertOk();
        $this->assertSame('dot-agent/1', json_decode($response->streamedContent(), true)['format']);
    }

    public function test_someone_without_the_role_cannot_create_or_set_up_an_agent(): void
    {
        $viewer = User::factory()->create(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $this->organization->users()->attach($viewer, ['role' => 'viewer']);
        $agent = $this->agent();

        $this->actingAs($viewer);

        $this->get(route('agents.create'))->assertForbidden();
        $this->get(route('agents.setup', $agent))->assertForbidden();
        $this->get(route('agents.export', $agent))->assertForbidden();
    }

    public function test_the_setup_itself_refuses_someone_without_the_role(): void
    {
        $viewer = User::factory()->create();
        $this->organization->users()->attach($viewer, ['role' => 'viewer']);
        $agent = $this->agent();

        Livewire::actingAs($viewer)->test(AgentSetup::class, ['deploymentId' => $agent->id])->assertForbidden();
    }

    public function test_the_setup_saves_to_a_draft_and_leaves_what_runs_alone(): void
    {
        $agent = $this->agent(['status' => 'active']);
        $running = $agent->publishedVersion->settings();

        Livewire::test(AgentSetup::class, ['deploymentId' => $agent->id])
            ->set('config.instructions', 'Check every invoice line and list what does not match.')
            ->set('tools.calculator', 'auto')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($running, $agent->fresh()->publishedVersion->settings());
        $this->assertSame([['key' => 'calculator', 'mode' => 'auto']], $agent->draftVersion()->first()->settings()['tools']);
    }

    public function test_publishing_puts_the_new_setup_and_autonomy_into_use(): void
    {
        $agent = $this->agent(['autonomy_level' => 1]);

        Livewire::test(AgentSetup::class, ['deploymentId' => $agent->id])
            ->set('config.instructions', 'Check every invoice line and list what does not match.')
            ->set('config.autonomy', 3)
            ->call('publish')
            ->assertHasNoErrors();

        $agent->refresh();
        $this->assertSame('active', $agent->status);
        $this->assertSame(3, (int) $agent->autonomy_level);
    }

    public function test_changing_autonomy_in_a_draft_changes_nothing_until_published(): void
    {
        $agent = $this->agent(['status' => 'active', 'autonomy_level' => 1]);

        Livewire::test(AgentSetup::class, ['deploymentId' => $agent->id])->set('config.autonomy', 4)->call('save');

        $this->assertSame(1, (int) $agent->fresh()->autonomy_level);
    }

    public function test_an_agent_with_a_blocker_cannot_be_published_even_past_the_button(): void
    {
        $agent = $this->agent();

        Livewire::test(AgentSetup::class, ['deploymentId' => $agent->id])
            ->set('config.instructions', '')
            ->call('publish')
            ->assertHasErrors('publish');

        $this->assertSame('draft', $agent->fresh()->status);
    }

    public function test_a_setup_cannot_reach_another_businesss_agent(): void
    {
        $theirs = TenantContext::system(fn () => AgentDeployment::factory()->create());

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(AgentSetup::class, ['deploymentId' => $theirs->id]);
    }

    public function test_the_review_finds_each_kind_of_problem(): void
    {
        $agent = $this->agent();
        $base = $this->emptyBase();

        $this->assertContains('It has no instructions', $this->titles($this->review(['instructions' => ''], $agent)));
        $this->assertContains('The instructions are very short', $this->titles($this->review(['instructions' => 'Do it.'], $agent)));
        $this->assertContains('Nobody can say what it is responsible for', $this->titles($this->review([], $this->agent(['responsibility' => '']))));
        $this->assertContains('Its responsibility is vague', $this->titles($this->review([], $this->agent(['responsibility' => 'Help with everything']))));
        $this->assertContains('It can change things without asking', $this->titles($this->review(['autonomy' => 4, 'tools' => [['key' => 'notify_member', 'mode' => 'auto']]], $agent)));
        $this->assertContains('It can read private information and also reach outside', $this->titles($this->review(['tools' => [['key' => 'team_directory', 'mode' => 'auto'], ['key' => 'web_read', 'mode' => 'auto']]], $agent)));
        $this->assertContains('Its instructions mention documents, but it has none', $this->titles($this->review(['instructions' => 'Answer from our policies and handbook, and stop when unsure.'], $agent)));
        $this->assertContains("{$base->name} has nothing ready to read", $this->titles($this->review(['knowledge' => [$base->id]], $agent)));
        $this->assertContains('made_up is not a tool that exists here', $this->titles($this->review(['tools' => [['key' => 'made_up', 'mode' => 'auto']]], $agent)));
        $this->assertContains('Its instructions contradict each other', $this->titles($this->review(['instructions' => 'Always refund the customer quickly. Never refund the customer without a manager.'], $agent)));
        $this->assertContains('It is set to Assist but has tools that change things', $this->titles($this->review(['autonomy' => 1, 'tools' => [['key' => 'notify_member', 'mode' => 'approve']]], $agent)));
        $this->assertContains('It is told to send something but has no tool to send with', $this->titles($this->review(['instructions' => 'Send the summary to the manager every time you finish a report.'], $agent)));
        $this->assertContains('It is told to remember, but long-term memory is off', $this->titles($this->review(['instructions' => 'Remember what each person prefers across all of the work you do.'], $agent)));
        $this->assertContains('Nobody owns this agent', $this->titles($this->review([], $this->agent(['owner_id' => null]))));
        $this->assertContains('Its spending limit per run is high', $this->titles($this->review(['limits' => ['cost' => 50]], $agent)));
        $this->assertContains('No AI provider is set up on this platform', $this->titles($this->review([], $agent)));
    }

    public function test_a_clean_setup_is_still_not_called_ready_without_a_test(): void
    {
        config(['prism.providers.anthropic.api_key' => 'test-only']);
        $agent = $this->agent();

        $review = $this->review(['tools' => [['key' => 'calculator', 'mode' => 'auto']]], $agent);

        $this->assertSame([], $review['findings']);
        $this->assertSame('untested', $review['verdict']);
        $this->assertStringNotContainsString('ready', strtolower($review['words']));
    }

    public function test_only_a_passing_test_of_this_exact_version_says_ready(): void
    {
        config(['prism.providers.anthropic.api_key' => 'test-only']);
        $agent = $this->agent();
        $version = $agent->versions()->create(['organization_id' => $agent->organization_id, 'number' => 2, 'state' => 'draft', 'config' => AgentConfig::normalise(['instructions' => 'Check each line of the invoice, then list what does not match.'])]);
        $review = fn (AgentConfigVersion $v) => app(AgentConfigReview::class)->review($agent, $v->settings(), $v)['verdict'];

        $this->recordRun($agent, $agent->publishedVersion, AgentRun::COMPLETED);
        $this->assertSame('untested', $review($version));

        $this->recordRun($agent, $version, AgentRun::FAILED);
        $this->assertSame('untested', $review($version));

        $this->recordRun($agent, $version, AgentRun::COMPLETED);
        $this->assertSame('ready', $review($version));
    }

    public function test_versions_say_what_changed_and_an_old_one_can_be_restored(): void
    {
        $agent = $this->agent(['status' => 'active']);
        $first = $agent->publishedVersion;
        DB::table('agent_config_versions')->where('id', $first->id)->update(['config' => encrypt(json_encode(['instructions' => 'Check each line of the invoice and report it.']), false)]);

        Livewire::test(AgentSetup::class, ['deploymentId' => $agent->id])
            ->set('config.instructions', 'Check every invoice line and list what does not match.')->call('publish');

        Livewire::test(AgentVersions::class, ['deploymentId' => $agent->id])
            ->assertSee('Changed instructions')
            ->call('restore', $first->id);

        $this->assertSame(3, $agent->fresh()->publishedVersion->number);
    }

    public function test_a_version_that_cannot_be_published_says_so_when_restored(): void
    {
        $agent = $this->agent(['status' => 'active']);
        $first = $agent->publishedVersion;

        Livewire::test(AgentSetup::class, ['deploymentId' => $agent->id])
            ->set('config.instructions', 'Check every invoice line and list what does not match.')->call('publish');

        Livewire::test(AgentVersions::class, ['deploymentId' => $agent->id])
            ->call('restore', $first->id)
            ->assertHasErrors('publish');

        $this->assertSame(2, $agent->fresh()->publishedVersion->number);
    }

    public function test_the_pages_render_for_someone_who_manages_agents(): void
    {
        $agent = $this->agent();

        $this->get(route('agents.create'))->assertOk()->assertSee('Describe it');
        $this->get(route('agents.setup', $agent))->assertOk()->assertSee('How much it may do alone')->assertSee('always go to a person');
        $this->get(route('agents.show', $agent))->assertOk()->assertSee('The job');
        $this->get(route('agents.deployments'))->assertOk()->assertSee($agent->name);
    }

    private function emptyBase(): KnowledgeBase
    {
        $base = new KnowledgeBase(['organization_id' => $this->organization->id, 'name' => 'Empty shelf', 'is_active' => true]);
        $base->slug = 'empty-shelf';
        $base->saveQuietly();

        return $base;
    }

    private function recordRun(AgentDeployment $agent, AgentConfigVersion $version, string $status): void
    {
        AgentRun::create(['organization_id' => $agent->organization_id, 'agent_deployment_id' => $agent->id, 'config_version_id' => $version->id, 'trigger_type' => 'test', 'status' => $status, 'input' => 'x', 'limits' => [], 'is_test' => true]);
    }

    public function test_a_template_records_where_an_agent_came_from_and_a_newer_version_is_mentioned(): void
    {
        Livewire::test(AgentCreate::class)->call('useTemplate', 'policy-helper');
        $agent = AgentDeployment::firstOrFail();
        config(['agent_templates.policy-helper.version' => 2]);

        Livewire::test(DeploymentOverview::class, ['deploymentId' => $agent->id])->assertSee('Version 2 of the template');

        $this->assertSame('policy-helper', $agent->draftVersion()->first()->settings()['template']['key']);
    }

    public function test_an_agent_can_be_kept_as_this_businesss_own_template_without_its_knowledge(): void
    {
        $agent = $this->agent(['name' => 'Our clerk']);
        $agent->versions()->create(['organization_id' => $agent->organization_id, 'number' => 2, 'state' => 'draft', 'config' => AgentConfig::normalise(['instructions' => 'Do it our way.', 'knowledge' => [9]])]);

        Livewire::test(DeploymentOverview::class, ['deploymentId' => $agent->id])->call('saveAsTemplate')->call('saveAsTemplate');

        $template = BusinessTemplate::latest('id')->firstOrFail();
        $this->assertSame(2, $template->version);
        $this->assertSame([], $template->config['knowledge']);

        Livewire::test(AgentCreate::class)->set('way', 'template')->assertSee('Our clerk')->call('useBusinessTemplate', $template->id);
        $this->assertSame('Do it our way.', AgentDeployment::latest('id')->first()->draftVersion()->first()->settings()['instructions']);
    }

    public function test_another_businesss_template_is_out_of_reach(): void
    {
        $theirs = TenantContext::system(function () {
            $agent = AgentDeployment::factory()->create();

            return BusinessTemplate::create(['organization_id' => $agent->organization_id, 'name' => 'Secret', 'config' => []]);
        });

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(AgentCreate::class)->call('useBusinessTemplate', $theirs->id);
    }

    public function test_the_library_lists_templates_with_what_they_need_and_no_popularity_figures(): void
    {
        Livewire::test(TemplateLibrary::class)
            ->assertSee('Invoice Checker')->assertSee('Needs:')->assertDontSee('rating')->assertDontSee('deployments')
            ->set('category', 'HR')->assertSee('Policy Helper')->assertDontSee('Invoice Checker');
    }

    public function test_the_library_says_what_each_tool_does_and_whether_it_is_available(): void
    {
        Livewire::test(TemplateLibrary::class)->call('setTab', 'tools')
            ->assertSee('Read a web page')->assertSee('Reaches:')->assertSee('Save a document to Dot.Files')->assertSee('Not connected');
    }

    public function test_every_category_the_brief_needs_first_has_a_working_template(): void
    {
        $categories = collect(config('agent_templates'))->pluck('category')->unique()->all();

        foreach (['Finance', 'HR', 'Operations', 'Administration', 'Customer support', 'Reporting'] as $category) {
            $this->assertContains($category, $categories);
        }

        foreach (config('agent_templates') as $template) {
            $this->assertNotEmpty($template['needs']);
            $this->assertArrayHasKey($template['autonomy'], AgentConfig::AUTONOMY);
        }
    }

    public function test_the_dot_files_tool_is_refused_until_it_is_connected(): void
    {
        config(['prism.default_provider' => 'scripted', 'services.files.secret' => null]);
        $agent = $this->agent(['status' => 'active', 'autonomy_level' => 4]);
        DB::table('agent_config_versions')->where('id', $agent->published_version_id)->update(['config' => encrypt(json_encode(['tools' => [['key' => 'save_to_files', 'mode' => 'auto']]]), false)]);
        $model = app(ScriptedGateway::class);
        $model->call('save_to_files', ['name' => 'Report', 'text' => 'Hello'])->say('It is not connected.');

        app(StartRunAction::class)->execute($agent->fresh(), new StartRunData('Save it'));

        $this->assertSame([], $model->requests[0]->tools);
        $this->assertSame('not_connected', AgentEffect::firstOrFail()->error_code);
    }

    public function test_the_dot_files_tool_signs_its_call_and_a_lost_answer_is_not_repeated(): void
    {
        config(['prism.default_provider' => 'scripted', 'services.files.secret' => 'shared-secret', 'services.files.url' => 'https://files.example']);
        $this->organization->forceFill(['infodot_team_uuid' => 'team-1'])->save();
        $agent = $this->agent(['status' => 'active', 'autonomy_level' => 4]);
        DB::table('agent_config_versions')->where('id', $agent->published_version_id)->update(['config' => encrypt(json_encode(['tools' => [['key' => 'save_to_files', 'mode' => 'auto']]]), false)]);
        Http::fake(['*' => Http::response(['reference' => 'DOC-1'], 201)]);
        $model = app(ScriptedGateway::class);
        $model->call('save_to_files', ['name' => 'Report', 'text' => 'Hello'])->say('Saved.');

        app(StartRunAction::class)->execute($agent->fresh(), new StartRunData('Save it'));

        Http::assertSent(function (Request $request) {
            $expected = 'sha256='.hash_hmac('sha256', $request->header('X-Dot-Timestamp')[0].'.'.$request->body(), 'shared-secret');

            return $request->url() === 'https://files.example/api/platform/files/store'
                && $request->header('X-Dot-Signature')[0] === $expected
                && $request['team_uuid'] === 'team-1'
                && $request->hasHeader('Idempotency-Key');
        });

        $this->assertStringContainsString('DOC-1', AgentEffect::firstOrFail()->result['content']);
    }

    public function test_a_finished_run_is_reported_to_dot_memory_and_a_test_is_not(): void
    {
        Queue::fake();
        $agent = $this->agent(['status' => 'active']);
        $run = AgentRun::create(['organization_id' => $agent->organization_id, 'agent_deployment_id' => $agent->id, 'config_version_id' => $agent->published_version_id, 'trigger_type' => 'manual', 'status' => 'running', 'input' => 'x', 'limits' => []]);
        $test = AgentRun::create(['organization_id' => $agent->organization_id, 'agent_deployment_id' => $agent->id, 'config_version_id' => $agent->published_version_id, 'trigger_type' => 'manual', 'status' => 'running', 'input' => 'x', 'limits' => [], 'is_test' => true]);

        app(RunFinisher::class)->complete($test, 'Done.');
        Queue::assertNotPushed(ReportRunToDotMemory::class);

        app(RunFinisher::class)->complete($run, 'Done.');
        Queue::assertPushed(ReportRunToDotMemory::class);
    }
}
