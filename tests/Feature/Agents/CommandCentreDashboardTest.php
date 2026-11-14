<?php

namespace Tests\Feature\Agents;

use App\Livewire\Dashboard\AgentDashboard;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Models\AgentTrigger;
use App\Models\Organization;
use App\Models\UsageBudget;
use App\Models\User;
use App\Support\OrgRoles;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The command centre as laid out in R-51: sections on rules, a state-true headline, and only figures counted from the records. */
class CommandCentreDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['name' => 'Karoo Freight']);
    }

    private function member(string $role): User
    {
        $user = User::factory()->create(['name' => 'Thandiwe Mokoena', 'consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $this->organization->users()->attach($user->id, ['role' => $role, 'is_primary' => true, 'joined_at' => now()]);

        return $user;
    }

    private function agent(string $name = 'Invoice checker', ?Organization $organization = null): AgentDeployment
    {
        return TenantContext::system(fn () => AgentDeployment::factory()->create(['organization_id' => ($organization ?? $this->organization)->id, 'status' => 'active', 'name' => $name]));
    }

    private function work(AgentDeployment $agent, string $status, array $attributes = []): AgentRun
    {
        return TenantContext::system(fn () => AgentRun::create(['organization_id' => $agent->organization_id, 'agent_deployment_id' => $agent->id, 'config_version_id' => $agent->published_version_id, 'trigger_type' => 'manual', 'status' => $status, 'summary' => 'Check the invoices', 'input' => 'x', 'limits' => [], 'started_at' => now()->subMinutes(2), 'finished_at' => in_array($status, AgentRun::FINISHED, true) ? now() : null, ...$attributes]));
    }

    private function hold(AgentDeployment $agent, string $title = 'Send the follow-up email'): AgentApproval
    {
        $run = $this->work($agent, AgentRun::WAITING_APPROVAL);
        TenantContext::system(fn () => AgentRunStep::create(['organization_id' => $agent->organization_id, 'agent_run_id' => $run->id, 'sequence' => 1, 'kind' => AgentRunStep::APPROVAL, 'title' => 'Waiting for a person']));

        return TenantContext::system(fn () => AgentApproval::factory()->create(['organization_id' => $agent->organization_id, 'agent_deployment_id' => $agent->id, 'agent_run_id' => $run->id, 'status' => 'pending', 'title' => $title]));
    }

    private function page(User $user): TestResponse
    {
        return $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    private function schedule(AgentDeployment $agent, string $name, array $attributes = []): AgentTrigger
    {
        return TenantContext::system(fn () => AgentTrigger::create(['organization_id' => $agent->organization_id, 'target_type' => 'agent', 'target_id' => $agent->id, 'kind' => 'schedule', 'name' => $name, 'is_active' => true, 'settings' => ['frequency' => 'daily', 'time' => '08:00', 'timezone' => 'UTC'], 'next_run_at' => now()->addHours(5), ...$attributes]));
    }

    private function budget(float $limit, bool $stop = true, ?Organization $organization = null): void
    {
        TenantContext::system(fn () => UsageBudget::create(['organization_id' => ($organization ?? $this->organization)->id, 'monthly_limit' => $limit, 'warn_at_percent' => 80, 'stop_at_limit' => $stop]));
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return collect(OrgRoles::ALL)->mapWithKeys(fn (string $role) => [$role => [$role]])->all();
    }

    #[DataProvider('roles')]
    public function test_this_month_is_shown_only_to_roles_that_may_read_usage(string $role): void
    {
        $agent = $this->agent();
        $this->work($agent, AgentRun::COMPLETED, ['cost' => 2.5]);
        $response = $this->page($this->member($role));

        if (OrgRoles::allows($role, 'usage.view')) {
            $response->assertSee('This month');
            $this->assertSame(1, preg_match('/data-figure="spent">([^<]*)</', $response->getContent(), $found));
            $this->assertSame('2.50', $found[1]);
        } else {
            $response->assertDontSee('This month')->assertDontSee('data-figure="spent"', false);
        }

        $this->assertSame(1, substr_count($response->getContent(), 'data-primary-action'));
    }

    public function test_the_meter_and_limit_come_from_the_businesss_own_budget(): void
    {
        $agent = $this->agent();
        $this->work($agent, AgentRun::COMPLETED, ['cost' => 8]);
        $this->budget(10);

        $response = $this->page($this->member(OrgRoles::OWNER));

        $response->assertSee('80 percent of the monthly limit spent')->assertSee('Runs stop at the limit.');
        $this->assertSame(1, preg_match('/data-figure="limit">([^<]*)</', $response->getContent(), $found));
        $this->assertSame('10.00', $found[1]);
    }

    public function test_with_no_budget_it_offers_to_set_one_and_draws_no_meter(): void
    {
        $this->agent();

        $this->page($this->member(OrgRoles::OWNER))->assertSee('Set a limit')->assertDontSee('monthly limit spent');
    }

    public function test_another_businesss_spending_budget_and_schedules_never_appear(): void
    {
        $mine = $this->agent();
        $this->work($mine, AgentRun::COMPLETED, ['cost' => 1]);
        $other = Organization::factory()->create();
        $theirs = $this->agent('Their agent', $other);
        $this->work($theirs, AgentRun::COMPLETED, ['cost' => 500]);
        $this->budget(99999, true, $other);
        $this->schedule($theirs, 'Their private schedule');

        $response = $this->page($this->member(OrgRoles::OWNER));

        $response->assertDontSee('Their private schedule')->assertDontSee('Their agent')->assertDontSee('99,999')->assertSee('No schedule is set.');
        $this->assertSame(1, preg_match('/data-figure="spent">([^<]*)</', $response->getContent(), $found));
        $this->assertSame('1.00', $found[1]);
    }

    public function test_coming_up_lists_only_active_schedules_that_have_a_next_start_soonest_first_and_at_most_three(): void
    {
        $agent = $this->agent();
        $this->schedule($agent, 'Later one', ['next_run_at' => now()->addDays(2)]);
        $this->schedule($agent, 'Soonest one', ['next_run_at' => now()->addHour()]);
        $this->schedule($agent, 'Switched off', ['is_active' => false]);
        $this->schedule($agent, 'Never starts', ['next_run_at' => null]);
        $this->schedule($agent, 'Third one', ['next_run_at' => now()->addDays(3)]);
        $this->schedule($agent, 'Fourth one', ['next_run_at' => now()->addDays(4)]);

        $this->page($this->member(OrgRoles::OWNER))
            ->assertSeeInOrder(['Soonest one', 'Later one', 'Third one'])
            ->assertDontSee('Fourth one')->assertDontSee('Switched off')->assertDontSee('Never starts');
    }

    public function test_the_headline_says_what_is_true_for_each_state(): void
    {
        $agent = $this->agent();
        $owner = $this->member(OrgRoles::OWNER);
        $operator = $this->member(OrgRoles::OPERATOR);
        $approver = $this->member(OrgRoles::APPROVER);

        $this->page($approver)->assertSee('Nothing is waiting for approval.');
        $this->page($operator)->assertSee('Nothing is held for a person.');

        $this->hold($agent);
        $this->hold($agent, 'Pay the supplier');

        $this->page($owner)->assertSee('2 actions are held until a person answers.');
        $this->page($operator)->assertSee('2 actions are waiting for an approver.');
    }

    public function test_the_first_approval_row_is_marked_as_the_one_drawn_in_the_arrival(): void
    {
        $agent = $this->agent();
        $this->hold($agent, 'Send the letter');

        $html = $this->page($this->member(OrgRoles::OWNER))->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="true"'));
        $this->assertStringContainsString('Send the letter', $html);
        $this->assertStringContainsString('rail-stop held', $html);
    }

    public function test_the_held_diamond_on_the_rail_appears_only_while_something_is_held(): void
    {
        $agent = $this->agent();
        $user = $this->member(OrgRoles::OWNER);

        $this->page($user)->assertDontSee('rail-stop held', false);
    }

    public function test_a_calm_business_gets_one_line_instead_of_an_empty_needs_you(): void
    {
        $this->agent();

        $this->page($this->member(OrgRoles::OWNER))->assertSee('Nothing needs a person.')->assertDontSee('Needs you');
    }

    public function test_failed_work_from_the_last_day_is_listed_with_its_reason(): void
    {
        $this->work($this->agent(), AgentRun::FAILED, ['summary' => 'Read claim CL-2291', 'error_message' => 'The folder could not be opened.']);

        $this->page($this->member(OrgRoles::OWNER))->assertSee('Needs you')->assertSee('Read claim CL-2291')->assertSee('The folder could not be opened.');
    }

    public function test_approval_wait_is_counted_from_answered_approvals(): void
    {
        $agent = $this->agent();
        TenantContext::system(fn () => AgentApproval::factory()->create(['organization_id' => $this->organization->id, 'agent_deployment_id' => $agent->id, 'status' => 'approved', 'created_at' => now()->subMinutes(10), 'reviewed_at' => now()]));
        $this->work($agent, AgentRun::COMPLETED);

        $response = $this->page($this->member(OrgRoles::OWNER));

        $this->assertSame(1, preg_match('/data-figure="approval-wait"[^>]*>([^<]*)</', $response->getContent(), $found));
        $this->assertSame('10 min', trim($found[1]));
    }

    public function test_the_agents_table_merges_own_agents_and_busy_ones_and_stars_work(): void
    {
        $busy = $this->agent('Busy clerk');
        $quiet = $this->agent('Quiet drafter');
        $this->work($busy, AgentRun::COMPLETED);
        $this->work($busy, AgentRun::FAILED);
        $owner = $this->member(OrgRoles::OWNER);

        $component = fn () => Livewire::actingAs($owner);
        session(['current_organization_id' => $this->organization->id]);

        $component()->test(AgentDashboard::class)
            ->assertSee('Busy clerk')->assertSee('50%')
            ->call('toggleFavourite', $quiet->id)
            ->assertSeeInOrder(['Quiet drafter', 'Busy clerk']);
    }

    public function test_a_business_with_no_agents_shows_one_sentence_and_one_button(): void
    {
        $response = $this->page($this->member(OrgRoles::OWNER));

        $response->assertSee('Karoo Freight has no agents yet.')
            ->assertDontSee('aria-label="Agents"', false)->assertDontSee('data-figure="finished"', false)->assertDontSee('Under way');
        $this->assertSame(1, substr_count($response->getContent(), 'data-primary-action'));
        $this->assertSame(1, substr_count($response->getContent(), 'btn primary'));
    }

    public function test_the_dashboard_is_built_without_cards_or_the_old_marketing_headline(): void
    {
        $this->agent();

        $response = $this->page($this->member(OrgRoles::OWNER));

        $response->assertDontSee('class="card', false)->assertDontSee('Agents do the work. You make the decisions.');
    }

    public function test_the_dashboard_adds_no_script_or_inline_event_handlers(): void
    {
        $this->agent();

        $html = $this->page($this->member(OrgRoles::OWNER))->getContent();
        $main = substr($html, (int) strpos($html, 'id="content"'));
        $main = substr($main, 0, (int) strpos($main, '<script'));

        $this->assertStringNotContainsString(' onclick=', $main);
        $this->assertSame(0, preg_match('/<script/', $main));
    }

    public function test_the_lamp_strip_counts_only_this_businesss_records_and_the_chart_names_its_busiest_day(): void
    {
        $agent = $this->agent();
        $this->hold($agent);
        $this->work($agent, AgentRun::FAILED, ['finished_at' => now()->subHour(), 'error_message' => 'x']);
        $this->work($agent, AgentRun::COMPLETED);
        $other = Organization::factory()->create();
        $theirs = $this->agent('Their agent', $other);
        $this->hold($theirs, 'Their hold');
        $this->work($theirs, AgentRun::FAILED, ['finished_at' => now()->subHour(), 'error_message' => 'y']);

        $html = $this->page($this->member(OrgRoles::OWNER))->getContent();

        $this->assertSame(1, preg_match('/data-board>(.*?)<\/p>/s', $html, $strip));
        $this->assertStringContainsString('1 held', $strip[1]);
        $this->assertStringContainsString('1 failed today', $strip[1]);
        $this->assertSame(1, preg_match('/data-busiest>(.*?)<\/p>/s', $html, $busiest));
        $this->assertStringContainsString('2 runs', strip_tags($busiest[1]));
    }

    public function test_the_lamp_strip_is_absent_while_there_are_no_agents(): void
    {
        $this->page($this->member(OrgRoles::OWNER))->assertDontSee('data-board', false);
    }
}
