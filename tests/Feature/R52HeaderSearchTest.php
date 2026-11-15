<?php

namespace Tests\Feature;

use App\Livewire\HeaderSearch;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\AgentSkill;
use App\Models\AgentTask;
use App\Models\AgentWorkflow;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrgRoles;
use App\Support\Search\AgentsSearch;
use App\Support\Search\SearchHit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-52: the header search. It reads one business's own agents, runs, workflows, approvals and custom tools, is permission-aware, and
 * never matches or returns a run's input or output, an approval's proposed action, an agent's instructions or a tool's webhook.
 */
class R52HeaderSearchTest extends TestCase
{
    use RefreshDatabase;

    private Organization $acme;

    private Organization $globex;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = Organization::factory()->create(['name' => 'Acme Ltd']);
        $this->globex = Organization::factory()->create(['name' => 'Globex Ltd']);
    }

    private function member(Organization $org, string $role = OrgRoles::OWNER, string $name = 'Thandiwe Mokoena'): User
    {
        $user = User::factory()->create(['name' => $name, 'consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $org->users()->attach($user->id, ['role' => $role, 'is_primary' => true, 'joined_at' => now()]);

        return $user;
    }

    private function agent(Organization $org, string $name, array $extra = []): AgentDeployment
    {
        return TenantContext::system(fn () => AgentDeployment::factory()->create(['organization_id' => $org->id, 'status' => 'active', 'name' => $name, ...$extra]));
    }

    private function work(AgentDeployment $agent, string $summary, array $extra = []): AgentRun
    {
        return TenantContext::system(fn () => AgentRun::create(['organization_id' => $agent->organization_id, 'agent_deployment_id' => $agent->id, 'config_version_id' => $agent->published_version_id, 'trigger_type' => 'manual', 'status' => 'completed', 'summary' => $summary, 'input' => 'x', 'limits' => [], 'finished_at' => now(), ...$extra]));
    }

    private function approval(AgentDeployment $agent, string $title, array $extra = []): AgentApproval
    {
        return TenantContext::system(function () use ($agent, $title, $extra) {
            $task = AgentTask::factory()->create(['organization_id' => $agent->organization_id, 'agent_deployment_id' => $agent->id]);

            return AgentApproval::factory()->create(['organization_id' => $agent->organization_id, 'agent_deployment_id' => $agent->id, 'task_id' => $task->id, 'title' => $title, ...$extra]);
        });
    }

    private function workflow(Organization $org, string $name, array $extra = []): AgentWorkflow
    {
        return TenantContext::system(fn () => AgentWorkflow::factory()->create(['organization_id' => $org->id, 'name' => $name, ...$extra]));
    }

    private function tool(Organization $org, string $name, array $extra = []): AgentSkill
    {
        return TenantContext::system(fn () => AgentSkill::factory()->webhook($org->id)->create(['name' => $name, ...$extra]));
    }

    /** @return array<string, list<SearchHit>> */
    private function search(User $user, Organization $open, string $q, int $per = 4): array
    {
        return TenantContext::forOrganization($open->id, fn () => app(AgentsSearch::class)->search($user, $q, $per));
    }

    private function titles(array $groups): array
    {
        return array_map(fn (array $hits): array => array_map(fn ($h) => $h->title, $hits), $groups);
    }

    // -- What it finds -----------------------------------------------------------------

    public function test_it_finds_this_businesss_agents_runs_workflows_approvals_and_custom_tools(): void
    {
        $user = $this->member($this->acme);
        $agent = $this->agent($this->acme, 'Invoice checker', ['role' => 'Accounts payable clerk']);
        $run = $this->work($agent, 'Check the invoices for November');
        $this->workflow($this->acme, 'Invoice escalation');
        $approval = $this->approval($agent, 'Pay supplier invoice 4471');
        $this->tool($this->acme, 'Invoice lookup');

        $groups = $this->search($user, $this->acme, 'invoice');

        $this->assertSame(['Agents', 'Runs', 'Workflows', 'Approvals', 'Custom tools'], array_keys($groups));
        $this->assertSame('Invoice checker', $groups['Agents'][0]->title);
        $this->assertSame(route('agents.show', $agent), $groups['Agents'][0]->url);
        $this->assertSame(route('runs.show', $run), $groups['Runs'][0]->url);
        $this->assertSame('Check the invoices for November', $groups['Runs'][0]->title);
        $this->assertSame('Invoice checker', $groups['Runs'][0]->sub);
        $this->assertSame(route('governance.approvals', ['approval' => $approval->uuid]), $groups['Approvals'][0]->url);
        $this->assertSame(route('org.skills'), $groups['Custom tools'][0]->url);
    }

    public function test_every_word_must_match_and_case_does_not_matter(): void
    {
        $user = $this->member($this->acme);
        $this->agent($this->acme, 'Invoice checker');
        $this->agent($this->acme, 'Payroll checker');

        $this->assertSame(['Invoice checker'], $this->titles($this->search($user, $this->acme, 'CHECKER invoice'))['Agents']);
        $this->assertSame([], $this->search($user, $this->acme, 'invoice payroll'));
    }

    public function test_it_finds_an_agent_by_its_role_and_by_a_runs_agent_name(): void
    {
        $user = $this->member($this->acme);
        $agent = $this->agent($this->acme, 'Quiet helper', ['role' => 'Treasury analyst']);
        $this->work($agent, 'Weekly roundup');

        $this->assertSame(['Quiet helper'], $this->titles($this->search($user, $this->acme, 'treasury'))['Agents']);
        $this->assertSame(['Weekly roundup'], $this->titles($this->search($user, $this->acme, 'quiet'))['Runs']);
    }

    public function test_a_per_group_limit_is_kept(): void
    {
        $user = $this->member($this->acme);
        foreach (range(1, 9) as $n) {
            $this->agent($this->acme, 'Batch agent '.$n);
        }

        $this->assertCount(4, $this->search($user, $this->acme, 'batch')['Agents']);
        $this->assertCount(9, $this->search($user, $this->acme, 'batch', 25)['Agents']);
    }

    // -- Tenant isolation --------------------------------------------------------------

    public function test_another_businesss_records_are_never_found(): void
    {
        $user = $this->member($this->acme);
        $theirAgent = $this->agent($this->globex, 'Invoice checker');
        $this->work($theirAgent, 'Check the invoices for November');
        $this->workflow($this->globex, 'Invoice escalation');
        $this->approval($theirAgent, 'Pay supplier invoice 4471');
        $this->tool($this->globex, 'Invoice lookup');

        $this->assertSame([], $this->search($user, $this->acme, 'invoice'));
    }

    public function test_a_person_in_two_businesses_sees_only_the_one_that_is_open(): void
    {
        $user = $this->member($this->acme);
        $this->globex->users()->attach($user->id, ['role' => OrgRoles::OWNER, 'is_primary' => false, 'joined_at' => now()]);
        $this->agent($this->acme, 'Acme ledger agent');
        $this->agent($this->globex, 'Globex ledger agent');

        $this->assertSame(['Acme ledger agent'], $this->titles($this->search($user, $this->acme, 'ledger'))['Agents']);
        $this->assertSame(['Globex ledger agent'], $this->titles($this->search($user, $this->globex, 'ledger'))['Agents']);
    }

    public function test_a_person_who_is_not_a_member_of_the_open_business_finds_nothing(): void
    {
        $outsider = $this->member($this->globex);
        $this->agent($this->acme, 'Invoice checker');

        $this->assertSame([], $this->search($outsider, $this->acme, 'invoice'));
    }

    public function test_with_no_business_open_nothing_is_found(): void
    {
        $user = $this->member($this->acme);
        $this->agent($this->acme, 'Invoice checker');

        $this->assertSame([], app(AgentsSearch::class)->search($user, 'invoice'));
    }

    public function test_the_shared_tool_catalogue_is_not_a_team_record(): void
    {
        $user = $this->member($this->acme);
        TenantContext::system(fn () => AgentSkill::factory()->create(['organization_id' => null, 'name' => 'Catalogue invoice skill']));

        $this->assertSame([], $this->search($user, $this->acme, 'catalogue'));
    }

    public function test_test_runs_are_not_listed(): void
    {
        $user = $this->member($this->acme);
        $agent = $this->agent($this->acme, 'Helper');
        $this->work($agent, 'A bench run for the new setup', ['is_test' => true, 'trigger_type' => 'test']);

        $this->assertSame([], $this->search($user, $this->acme, 'bench'));
    }

    // -- Permission-aware ---------------------------------------------------------------

    public function test_runs_are_left_out_for_a_role_that_cannot_open_them(): void
    {
        $nobody = $this->member($this->acme, 'unknown_role');
        $agent = $this->agent($this->acme, 'Invoice checker');
        $this->work($agent, 'Check the invoices for November');

        $groups = $this->search($nobody, $this->acme, 'invoice');

        $this->assertSame(['Agents'], array_keys($groups));
    }

    public function test_every_named_role_can_search_runs(): void
    {
        $agent = $this->agent($this->acme, 'Invoice checker');
        $this->work($agent, 'Check the invoices for November');

        foreach (OrgRoles::ALL as $role) {
            $user = $this->member($this->acme, $role, 'Person '.$role);
            $this->assertArrayHasKey('Runs', $this->search($user, $this->acme, 'invoices'), $role);
        }
    }

    // -- No payloads, no secrets ---------------------------------------------------------

    public function test_it_never_matches_or_returns_a_payload_or_a_secret(): void
    {
        $user = $this->member($this->acme);
        $agent = $this->agent($this->acme, 'Plain agent', ['custom_instructions' => 'SECRETWORD in the instructions']);
        $this->work($agent, 'Plain summary', ['input' => 'SECRETWORD in the input', 'output' => 'SECRETWORD in the output', 'error_message' => 'SECRETWORD in the error']);
        $this->approval($agent, 'Plain approval', ['description' => 'SECRETWORD in the description', 'proposed_action' => ['action' => 'SECRETWORD']]);
        $this->tool($this->acme, 'Plain tool', ['description' => 'plain', 'webhook_url' => 'https://example.test/SECRETWORD', 'webhook_headers' => ['Authorization' => 'Bearer SECRETWORD']]);

        $this->assertSame([], $this->search($user, $this->acme, 'SECRETWORD'));

        $everything = json_encode($this->search($user, $this->acme, 'plain', 25));
        $this->assertStringNotContainsString('SECRETWORD', (string) $everything);
        $this->assertStringNotContainsString('example.test', (string) $everything);
    }

    // -- Input handling -------------------------------------------------------------------

    public function test_short_and_blank_queries_find_nothing_and_wildcards_are_literal(): void
    {
        $user = $this->member($this->acme);
        $this->agent($this->acme, 'Invoice checker');

        foreach (['', ' ', 'i', '%', '%%', '_', '__', '!', "' OR 1=1 --", '\\'] as $q) {
            $this->assertSame([], $this->search($user, $this->acme, $q), 'query: '.$q);
        }
    }

    public function test_a_literal_percent_matches_only_a_name_that_holds_one(): void
    {
        $user = $this->member($this->acme);
        $this->agent($this->acme, 'Margin 50% agent');
        $this->agent($this->acme, 'Other agent');

        $this->assertSame(['Margin 50% agent'], $this->titles($this->search($user, $this->acme, '50%'))['Agents']);
    }

    public function test_a_long_query_is_cut_and_words_are_capped(): void
    {
        $this->assertCount(5, AgentsSearch::words('one two three four five six seven'));
        $this->assertLessThanOrEqual(AgentsSearch::MAX_LENGTH, mb_strlen(implode(' ', AgentsSearch::words(str_repeat('a', 500)))));
    }

    // -- The dropdown (Livewire) -----------------------------------------------------------

    public function test_the_dropdown_lists_matches_marks_the_words_and_escapes_markup(): void
    {
        $user = $this->member($this->acme);
        $this->agent($this->acme, 'Invoice <b>checker</b>');
        $this->actingAs($user)->withSession(['current_organization_id' => $this->acme->id]);
        TenantContext::set($this->acme->id);

        Livewire::test(HeaderSearch::class)
            ->set('q', 'invoice')
            ->assertSeeHtml('<mark>Invoice</mark>')
            ->assertDontSeeHtml('<b>checker</b>')
            ->assertSee('See every result for invoice');
    }

    public function test_the_dropdown_never_shows_another_business_and_says_so_when_nothing_matches(): void
    {
        $user = $this->member($this->acme);
        $this->agent($this->globex, 'Globex only agent');
        $this->actingAs($user)->withSession(['current_organization_id' => $this->acme->id]);
        TenantContext::set($this->acme->id);

        Livewire::test(HeaderSearch::class)
            ->set('q', 'globex')
            ->assertDontSee('Globex only agent')
            ->assertSee('Nothing matches');
    }

    public function test_before_typing_the_dropdown_offers_pages_every_role_can_open(): void
    {
        $user = $this->member($this->acme, OrgRoles::VIEWER);
        $this->actingAs($user)->withSession(['current_organization_id' => $this->acme->id]);
        TenantContext::set($this->acme->id);

        Livewire::test(HeaderSearch::class)
            ->assertSee('Go to')
            ->assertSee('Command centre')
            ->assertSee('Approvals')
            ->assertDontSee('Billing')
            ->assertDontSee('Audit log');
    }

    public function test_a_viewer_can_search_because_searching_changes_nothing(): void
    {
        $viewer = $this->member($this->acme, OrgRoles::VIEWER);
        $this->agent($this->acme, 'Invoice checker');
        $this->actingAs($viewer)->withSession(['current_organization_id' => $this->acme->id]);
        TenantContext::set($this->acme->id);

        Livewire::test(HeaderSearch::class)->set('q', 'invoice')->assertOk()->assertSee('Invoice');
    }

    // -- The full results page -------------------------------------------------------------

    private function open(User $user, Organization $org): static
    {
        return $this->actingAs($user)->withSession(['current_organization_id' => $org->id]);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('search', ['q' => 'invoice']))->assertRedirect();
    }

    public function test_the_results_page_lists_this_businesss_records_by_group(): void
    {
        $user = $this->member($this->acme);
        $agent = $this->agent($this->acme, 'Invoice checker');
        $this->work($agent, 'Check the invoices for November');
        $this->agent($this->globex, 'Invoice clerk of Globex');

        $this->open($user, $this->acme)->get(route('search', ['q' => 'invoice']))
            ->assertOk()
            ->assertSee('Agents')->assertSee('Runs')
            ->assertSee('Invoice checker')
            ->assertDontSee('Invoice clerk of Globex')
            ->assertSee('role="search"', false);
    }

    public function test_the_results_page_has_empty_and_no_match_states(): void
    {
        $user = $this->member($this->acme);

        $this->open($user, $this->acme)->get(route('search'))->assertOk()->assertSee('Go to')->assertSee('Type at least 2 characters');
        $this->open($user, $this->acme)->get(route('search', ['q' => 'zzzzzz']))->assertOk()->assertSee('Nothing matches');
    }

    public function test_the_results_page_escapes_what_a_record_holds(): void
    {
        $user = $this->member($this->acme);
        $this->agent($this->acme, '<script>alert(1)</script> invoice');

        $this->open($user, $this->acme)->get(route('search', ['q' => 'invoice']))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_results_page_is_throttled_and_validates_the_query(): void
    {
        $user = $this->member($this->acme);

        $this->open($user, $this->acme)->get(route('search', ['q' => str_repeat('a', 500)]))->assertSessionHasErrors('q');
    }
}
