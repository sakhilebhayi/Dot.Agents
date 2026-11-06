<?php

namespace Tests\Feature\Agents;

use App\Actions\Agents\StartRunAction;
use App\Actions\Billing\SaveUsageBudgetAction;
use App\DTOs\Agents\StartRunData;
use App\DTOs\Billing\SaveUsageBudgetData;
use App\Exceptions\RunRefused;
use App\Livewire\Dashboard\AgentDashboard;
use App\Livewire\Runs\RunCentre;
use App\Livewire\Runs\RunDetail;
use App\Livewire\UsageOverview;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Models\AgentTrigger;
use App\Models\AgentUsageEvent;
use App\Models\ModelPrice;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\BudgetNotification;
use App\Services\Insights\BudgetWatch;
use App\Services\Insights\RunDiagnosis;
use App\Services\Insights\RunMetrics;
use App\Services\Models\ScriptedGateway;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CommandCentreTest extends TestCase
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
        $this->agent = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active', 'enable_memory' => false, 'name' => 'Clerk', 'owner_id' => $this->owner->id]);
    }

    private function finishedRun(string $status, array $attributes = []): AgentRun
    {
        return AgentRun::create(['organization_id' => $this->organization->id, 'agent_deployment_id' => $this->agent->id, 'config_version_id' => $this->agent->published_version_id, 'trigger_type' => 'manual', 'status' => $status, 'summary' => 'Some work', 'input' => 'x', 'limits' => [], 'started_at' => now()->subMinutes(2), 'finished_at' => in_array($status, AgentRun::FINISHED, true) ? now() : null, ...$attributes]);
    }

    private function metrics(): array
    {
        return app(RunMetrics::class)->summary($this->organization->id, now()->subDays(7));
    }

    public function test_figures_are_counted_from_real_runs(): void
    {
        $this->finishedRun(AgentRun::COMPLETED, ['tokens_used' => 100, 'cost' => 0.5]);
        $this->finishedRun(AgentRun::COMPLETED, ['tokens_used' => 50, 'cost' => 0.25]);
        $this->finishedRun(AgentRun::FAILED, ['cost' => 0.1, 'wasted_cost' => 0.1]);
        $this->finishedRun(AgentRun::STOPPED);
        $this->finishedRun(AgentRun::CANCELLED);

        $metrics = $this->metrics();

        $this->assertSame(5, $metrics['finished']);
        $this->assertSame(2, $metrics['completed']);
        $this->assertSame(2, $metrics['failed']);
        $this->assertSame(50.0, $metrics['rate']);
        $this->assertSame(150, $metrics['tokens']);
        $this->assertEqualsWithDelta(0.85, $metrics['cost'], 0.001);
        $this->assertEqualsWithDelta(0.1, $metrics['wasted'], 0.001);
        $this->assertSame(120, $metrics['duration']);
    }

    public function test_with_nothing_to_count_a_rate_is_empty_not_zero(): void
    {
        $metrics = $this->metrics();

        $this->assertSame(0, $metrics['finished']);
        $this->assertNull($metrics['rate']);
        $this->assertNull($metrics['duration']);
        $this->assertNull($metrics['tool_failures']);
        $this->assertNull($metrics['approval_delay']);
    }

    public function test_tests_and_open_runs_and_old_runs_are_left_out(): void
    {
        $this->finishedRun(AgentRun::COMPLETED, ['is_test' => true]);
        $this->finishedRun(AgentRun::RUNNING);
        $this->finishedRun(AgentRun::COMPLETED, ['finished_at' => now()->subDays(30)]);

        $this->assertSame(0, $this->metrics()['finished']);
    }

    public function test_another_businesss_runs_are_not_counted(): void
    {
        TenantContext::system(function () {
            $theirs = AgentDeployment::factory()->create(['status' => 'active']);
            AgentRun::create(['organization_id' => $theirs->organization_id, 'agent_deployment_id' => $theirs->id, 'config_version_id' => $theirs->published_version_id, 'trigger_type' => 'manual', 'status' => 'completed', 'input' => 'x', 'limits' => [], 'finished_at' => now()]);
        });

        $this->assertSame(0, $this->metrics()['finished']);
    }

    public function test_tool_failure_rate_retries_and_approval_delay_come_from_the_record(): void
    {
        $run = $this->finishedRun(AgentRun::COMPLETED);
        foreach ([AgentEffect::DONE, AgentEffect::DONE, AgentEffect::DONE, AgentEffect::FAILED] as $i => $status) {
            AgentEffect::create(['organization_id' => $this->organization->id, 'agent_run_id' => $run->id, 'call_id' => "c{$i}", 'tool_key' => 'calculator', 'idempotency_key' => "k{$i}", 'status' => $status, 'arguments' => []]);
        }
        AgentRunStep::create(['organization_id' => $this->organization->id, 'agent_run_id' => $run->id, 'sequence' => 1, 'kind' => AgentRunStep::MODEL, 'status' => 'failed', 'title' => 'x']);
        AgentApproval::factory()->create(['organization_id' => $this->organization->id, 'agent_deployment_id' => $this->agent->id, 'created_at' => now()->subMinutes(10), 'reviewed_at' => now(), 'status' => 'approved']);

        $metrics = $this->metrics();

        $this->assertSame(25.0, $metrics['tool_failures']);
        $this->assertSame(1, $metrics['retries']);
        $this->assertEqualsWithDelta(600, $metrics['approval_delay'], 2);
    }

    public function test_calls_with_no_price_are_counted_and_called_out(): void
    {
        $run = $this->finishedRun(AgentRun::COMPLETED);
        AgentUsageEvent::create(['organization_id' => $this->organization->id, 'agent_deployment_id' => $this->agent->id, 'agent_run_id' => $run->id, 'provider' => 'anthropic', 'model' => 'x', 'input_tokens' => 10, 'output_tokens' => 5, 'cost' => null]);

        Livewire::test(UsageOverview::class)->assertSee('had no price on file')->assertSee('No price');
    }

    public function test_quiet_days_appear_in_the_daily_figures(): void
    {
        $this->finishedRun(AgentRun::COMPLETED, ['finished_at' => now()->subDays(2)]);
        $this->finishedRun(AgentRun::FAILED);

        $daily = app(RunMetrics::class)->daily($this->organization->id, now()->subDays(13));

        $this->assertCount(14, $daily);
        $this->assertSame(1, end($daily)['failed']);
        $this->assertSame(1, $daily[11]['completed']);
        $this->assertSame(0, $daily[12]['completed']);
    }

    public function test_the_command_centre_shows_what_needs_a_person(): void
    {
        $this->finishedRun(AgentRun::FAILED, ['error_message' => 'The provider said no.', 'summary' => 'Broken work']);
        $this->finishedRun(AgentRun::RUNNING, ['summary' => 'Busy work']);
        AgentApproval::factory()->create(['organization_id' => $this->organization->id, 'agent_deployment_id' => $this->agent->id, 'status' => 'pending', 'title' => 'Send the letter']);

        Livewire::test(AgentDashboard::class)
            ->assertSee('Needs you')->assertSee('Send the letter')->assertSee('Broken work')->assertSee('Busy work');
    }

    public function test_the_command_centre_is_calm_when_there_is_nothing(): void
    {
        Livewire::test(AgentDashboard::class)->assertDontSee('Needs you')->assertSee('Nothing is running right now.');
    }

    public function test_an_agent_can_be_starred_and_shows_up_first(): void
    {
        $other = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Starred one']);

        Livewire::test(AgentDashboard::class)->call('toggleFavourite', $other->id)->assertSeeInOrder(['Starred one', 'Clerk']);

        $this->assertSame([$other->id], $this->owner->favouriteAgents()->allRelatedIds()->all());
    }

    public function test_another_businesss_agent_cannot_be_starred(): void
    {
        $theirs = TenantContext::system(fn () => AgentDeployment::factory()->create());

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(AgentDashboard::class)->call('toggleFavourite', $theirs->id);
    }

    public function test_the_runs_page_sorts_work_by_where_it_stands(): void
    {
        $this->finishedRun(AgentRun::RUNNING, ['summary' => 'Busy work']);
        $this->finishedRun(AgentRun::FAILED, ['summary' => 'Broken work']);
        AgentTrigger::create(['organization_id' => $this->organization->id, 'target_type' => 'agent', 'target_id' => $this->agent->id, 'kind' => 'schedule', 'name' => 'Monday report', 'settings' => ['frequency' => 'daily', 'time' => '08:00', 'timezone' => 'UTC'], 'next_run_at' => now()->addDay()]);

        Livewire::test(RunCentre::class)
            ->assertSee('Busy work')->assertDontSee('Broken work')
            ->call('setTab', 'failed')->assertSee('Broken work')->assertDontSee('Busy work')
            ->call('setTab', 'scheduled')->assertSee('Monday report');
    }

    public function test_a_run_shows_its_whole_story(): void
    {
        $this->model->say('The total is 40.');
        $run = app(StartRunAction::class)->execute($this->agent, new StartRunData('Add it up', initiatedBy: $this->owner->id))->refresh();

        $this->get(route('runs.show', $run))->assertOk()->assertSee('The total is 40.')->assertSee('What happened')->assertSee('Technical details');
    }

    public function test_nobody_outside_the_business_can_open_a_run(): void
    {
        $run = $this->finishedRun(AgentRun::COMPLETED);
        $outsider = User::factory()->create(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $theirs = Organization::factory()->create();
        $theirs->users()->attach($outsider, ['role' => 'owner']);
        session(['current_organization_id' => $theirs->id]);

        $this->actingAs($outsider)->get(route('runs.show', $run))->assertNotFound();
    }

    public function test_a_failure_is_explained_and_says_what_to_do(): void
    {
        $run = $this->finishedRun(AgentRun::STOPPED, ['error_code' => 'limit_cost', 'error_message' => 'Stopped at the spending limit.']);

        $diagnosis = app(RunDiagnosis::class)->for($run);

        $this->assertStringContainsString('spending limit', $diagnosis['what']);
        $this->assertTrue($diagnosis['recoverable']);
        $this->assertStringContainsString('Raise the limit', $diagnosis['action']);
        $this->assertNull(app(RunDiagnosis::class)->for($this->finishedRun(AgentRun::COMPLETED)));
    }

    public function test_a_cause_the_platform_does_not_know_is_not_guessed_at(): void
    {
        $diagnosis = app(RunDiagnosis::class)->for($this->finishedRun(AgentRun::FAILED, ['error_code' => 'tool_failed', 'error_message' => 'The exact words of the failure.']));

        $this->assertSame('The exact words of the failure.', $diagnosis['what']);
    }

    public function test_a_failed_run_can_be_tried_again_and_stays_on_the_record(): void
    {
        $run = $this->finishedRun(AgentRun::FAILED, ['error_code' => 'overloaded']);
        $this->model->say('Worked this time.');

        Livewire::test(RunDetail::class, ['runId' => $run->id])->call('retry');

        $this->assertSame(2, AgentRun::count());
        $this->assertSame(AgentRun::FAILED, $run->fresh()->status);
    }

    public function test_what_cannot_be_recovered_offers_no_retry(): void
    {
        $run = $this->finishedRun(AgentRun::FAILED, ['error_code' => 'no_provider']);

        Livewire::test(RunDetail::class, ['runId' => $run->id])->assertDontSee('Try again')->assertSee('Not from here');
    }

    public function test_a_viewer_cannot_retry(): void
    {
        $run = $this->finishedRun(AgentRun::FAILED, ['error_code' => 'overloaded']);
        $viewer = User::factory()->create();
        $this->organization->users()->attach($viewer, ['role' => 'viewer']);

        Livewire::actingAs($viewer)->test(RunDetail::class, ['runId' => $run->id])->call('retry')->assertForbidden();
    }

    public function test_a_retry_keeps_what_the_work_was_exposed_to(): void
    {
        Queue::fake();
        $run = $this->finishedRun(AgentRun::FAILED, ['error_code' => 'overloaded', 'taint' => 3]);

        Livewire::test(RunDetail::class, ['runId' => $run->id])->call('retry');

        $this->assertSame(3, AgentRun::latest('id')->first()->taint);
    }

    public function test_a_budget_stops_new_work_at_the_limit(): void
    {
        $this->finishedRun(AgentRun::COMPLETED, ['cost' => 10]);
        app(SaveUsageBudgetAction::class)->execute($this->organization->id, new SaveUsageBudgetData($this->owner, 10, 80, true));

        $this->expectException(RunRefused::class);
        app(StartRunAction::class)->execute($this->agent, new StartRunData('Go'));
    }

    public function test_a_budget_set_to_warn_only_lets_work_carry_on(): void
    {
        Queue::fake();
        $this->finishedRun(AgentRun::COMPLETED, ['cost' => 10]);
        app(SaveUsageBudgetAction::class)->execute($this->organization->id, new SaveUsageBudgetData($this->owner, 10, 80, false));

        $this->assertNotNull(app(StartRunAction::class)->execute($this->agent, new StartRunData('Go')));
    }

    public function test_last_months_spending_does_not_count(): void
    {
        $this->finishedRun(AgentRun::COMPLETED, ['cost' => 100, 'created_at' => now()->subMonths(2)]);

        $this->assertSame(0.0, app(BudgetWatch::class)->spentThisMonth($this->organization->id));
    }

    public function test_the_people_who_pay_are_warned_once_a_month(): void
    {
        Notification::fake();
        app(SaveUsageBudgetAction::class)->execute($this->organization->id, new SaveUsageBudgetData($this->owner, 10, 80, true));
        $this->finishedRun(AgentRun::COMPLETED, ['cost' => 8.5]);

        app(BudgetWatch::class)->check($this->organization->id);
        app(BudgetWatch::class)->check($this->organization->id);

        Notification::assertSentToTimes($this->owner, BudgetNotification::class, 1);
    }

    public function test_only_billing_managers_set_a_budget(): void
    {
        $viewer = User::factory()->create();
        $this->organization->users()->attach($viewer, ['role' => 'viewer']);

        $this->expectException(AuthorizationException::class);
        app(SaveUsageBudgetAction::class)->execute($this->organization->id, new SaveUsageBudgetData($viewer, 10, 80, true));
    }

    public function test_the_usage_page_shows_how_figures_are_counted(): void
    {
        $this->finishedRun(AgentRun::COMPLETED, ['cost' => 1.5, 'tokens_used' => 1000]);

        $this->get(route('usage'))->assertOk()->assertSee('How these are counted')->assertSee('1,000');
    }

    public function test_a_price_needs_a_source(): void
    {
        $this->artisan('agents:set-price', ['provider' => 'anthropic', 'model' => 'x', 'input' => 3, 'output' => 15])->assertFailed();
        $this->artisan('agents:set-price', ['provider' => 'anthropic', 'model' => 'x', 'input' => 3, 'output' => 15, '--source' => 'The price page'])->assertSuccessful();

        $this->assertSame('The price page', ModelPrice::firstOrFail()->source);
    }
}
