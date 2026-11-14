<?php

namespace Tests\Feature;

use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrgRoles;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Where a person lands from InfoDot: the logo, a welcome, one action that works for their role, and only real figures. */
class LandingExperienceTest extends TestCase
{
    use RefreshDatabase;

    private const LOGOS = [
        'dot.logos3-light.png' => 'b95b32085c44d675ccf33074cd8280036d979a3b64aaae1916875cbeabfecdb1',
        'coming-soon/dot.logos3.png' => 'd70a5d648420cbb42bd8cdbe83bc1a4c381fdc218154b6fa8126d590c610c2fa',
    ];

    private const PLATFORMS = ['agents', 'analytics', 'auction', 'central', 'charts', 'design', 'docs', 'dopemine', 'ehail', 'emall', 'engage', 'files', 'finance', 'forms', 'hr', 'memory', 'mines', 'notify', 'press', 'projects', 'pulse', 'sheet', 'tasks', 'tutor', 'infodot'];

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['name' => 'Karoo Freight']);
    }

    private function member(string $role, string $name = 'Thandiwe Mokoena'): User
    {
        $user = User::factory()->create(['name' => $name, 'consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $this->organization->users()->attach($user->id, ['role' => $role, 'is_primary' => true, 'joined_at' => now()]);

        return $user;
    }

    private function agent(string $name = 'Invoice checker'): AgentDeployment
    {
        return TenantContext::system(fn () => AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active', 'name' => $name]));
    }

    /** @param list<array{0: string, 1: string}> $steps */
    private function work(AgentDeployment $agent, string $status, array $steps = [], array $attributes = []): AgentRun
    {
        return TenantContext::system(function () use ($agent, $status, $steps, $attributes) {
            $run = AgentRun::create(['organization_id' => $agent->organization_id, 'agent_deployment_id' => $agent->id, 'config_version_id' => $agent->published_version_id, 'trigger_type' => 'manual', 'status' => $status, 'summary' => 'Check the invoices', 'input' => 'x', 'limits' => [], 'started_at' => now()->subMinutes(2), 'finished_at' => in_array($status, AgentRun::FINISHED, true) ? now() : null, ...$attributes]);

            foreach ($steps as $i => [$kind, $title]) {
                AgentRunStep::create(['organization_id' => $agent->organization_id, 'agent_run_id' => $run->id, 'sequence' => $i + 1, 'kind' => $kind, 'title' => $title]);
            }

            return $run;
        });
    }

    private function hold(AgentDeployment $agent, string $title = 'Send the follow-up email'): AgentApproval
    {
        $run = $this->work($agent, AgentRun::WAITING_APPROVAL, [[AgentRunStep::SYSTEM, 'Started from manual'], [AgentRunStep::APPROVAL, 'Waiting for a person']]);

        return TenantContext::system(fn () => AgentApproval::factory()->create(['organization_id' => $agent->organization_id, 'agent_deployment_id' => $agent->id, 'agent_run_id' => $run->id, 'status' => 'pending', 'title' => $title]));
    }

    /** @return array{0: string, 1: string, 2: string} The state, the words and the address of the one primary action. */
    private function primary(TestResponse $response): array
    {
        $html = (string) $response->getContent();

        $this->assertSame(1, substr_count($html, 'data-primary-action'), 'The landing must carry exactly one primary action.');
        $this->assertSame(1, preg_match('/<a class="btn primary lead" href="([^"]+)" data-primary-action data-state="([^"]+)">([^<]+)<\/a>/', $html, $found));
        $this->assertSame(1, substr_count($html, 'btn primary'), 'Nothing else on the landing may look like the primary action.');

        return [$found[2], trim(html_entity_decode($found[3])), html_entity_decode($found[1])];
    }

    private function figure(TestResponse $response, string $name): string
    {
        $this->assertSame(1, preg_match('/data-figure="'.$name.'">([^<]*)</', (string) $response->getContent(), $found));

        return trim($found[1]);
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return collect(OrgRoles::ALL)->mapWithKeys(fn (string $role) => [$role => [$role]])->all();
    }

    #[DataProvider('roles')]
    public function test_every_role_lands_on_one_working_action_when_the_business_has_work(string $role): void
    {
        $agent = $this->agent();
        $this->work($agent, AgentRun::COMPLETED, [[AgentRunStep::SYSTEM, 'Started from manual'], [AgentRunStep::SYSTEM, 'Finished']]);
        $this->hold($agent);
        $user = $this->member($role);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        [$state, $label, $href] = $this->primary($response);

        $reviews = OrgRoles::allows($role, 'approvals.review');
        $this->assertSame($reviews ? 'review' : (OrgRoles::allows($role, 'agents.operate') ? 'work' : 'watch'), $state);
        $this->assertSame($reviews ? 'Review 1 approval' : ($state === 'work' ? 'Open your agents' : 'See what the agents are doing'), $label);
        $this->assertSame(route($reviews ? 'governance.approvals' : ($state === 'work' ? 'agents.deployments' : 'runs.index')), $href);
        $this->actingAs($user)->get($href)->assertOk();
    }

    #[DataProvider('roles')]
    public function test_every_role_lands_on_one_working_action_when_the_business_has_no_agents(string $role): void
    {
        $user = $this->member($role);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        [$state, $label, $href] = $this->primary($response);

        $builds = OrgRoles::allows($role, 'agents.manage');
        $this->assertSame($builds ? 'first-agent' : 'no-agents', $state);
        $this->assertSame($builds ? 'Set up your first agent' : 'See what agents can do', $label);
        $this->assertSame(route($builds ? 'agents.create' : 'marketplace'), $href);
        $this->actingAs($user)->get($href)->assertOk();
    }

    public function test_an_approver_with_nothing_waiting_is_sent_to_the_approval_desk(): void
    {
        $this->agent();
        $user = $this->member(OrgRoles::APPROVER);

        [$state, $label, $href] = $this->primary($this->actingAs($user)->get('/dashboard')->assertOk());

        $this->assertSame(['desk', 'Open the approval desk', route('governance.approvals')], [$state, $label, $href]);
        $this->actingAs($user)->get($href)->assertOk();
    }

    public function test_the_welcome_uses_the_persons_first_name_the_business_and_their_role(): void
    {
        $response = $this->actingAs($this->member(OrgRoles::AGENT_MANAGER, 'Lerato Nkosi'))->get('/dashboard')->assertOk();

        $response->assertSee('Welcome, Lerato. You are in Karoo Freight as agent manager.');
        $response->assertDontSee('Welcome, Lerato Nkosi');
        $response->assertSee('Agents do the work. You make the decisions.');
    }

    public function test_the_landing_shows_the_original_logo_files(): void
    {
        foreach (self::LOGOS as $file => $hash) {
            $this->assertFileExists(public_path($file));
            $this->assertSame($hash, hash_file('sha256', public_path($file)), "{$file} is not the original artwork.");
        }

        $response = $this->actingAs($this->member(OrgRoles::OWNER))->get('/dashboard')->assertOk();

        foreach (array_keys(self::LOGOS) as $file) {
            $response->assertSee('src="'.asset($file).'" alt="Dot.Agents"', false);
        }
    }

    public function test_the_way_back_to_infodot_comes_from_the_ecosystem_configuration(): void
    {
        config(['ecosystem.platforms.infodot.url' => 'https://infodot.example/']);

        $response = $this->actingAs($this->member(OrgRoles::VIEWER))->get('/dashboard')->assertOk();

        $response->assertSee('<a href="https://infodot.example/home">InfoDot</a>', false);
        $response->assertSee('<a href="https://infodot.example/platforms">Platforms</a>', false);
    }

    public function test_a_business_with_nothing_yet_is_told_so_plainly(): void
    {
        $response = $this->actingAs($this->member(OrgRoles::OWNER, 'Sipho Dlamini'))->get('/dashboard')->assertOk();

        $response->assertSee('data-no-run', false)->assertDontSee('data-featured-run', false);
        $response->assertSee('No run yet.')->assertSee('Karoo Freight has no agents yet.')->assertSee('Nothing is running right now.');
        $response->assertDontSee('Needs you');

        $this->assertSame('0', $this->figure($response, 'finished'));
        $this->assertSame('–', $this->figure($response, 'rate'));
        $this->assertSame('0.00', $this->figure($response, 'cost'));
        $this->assertSame('–', $this->figure($response, 'duration'));
    }

    public function test_every_figure_on_the_landing_is_counted_from_the_records(): void
    {
        $agent = $this->agent();
        $this->work($agent, AgentRun::COMPLETED, attributes: ['cost' => 0.5]);
        $this->work($agent, AgentRun::COMPLETED, attributes: ['cost' => 0.25]);
        $this->work($agent, AgentRun::COMPLETED, attributes: ['cost' => 0.25]);
        $this->work($agent, AgentRun::FAILED, attributes: ['cost' => 0.1]);
        $this->work($agent, AgentRun::COMPLETED, attributes: ['cost' => 9, 'is_test' => true]);
        $this->hold($agent, 'Send the letter');
        $this->hold($agent, 'Pay the supplier');

        $response = $this->actingAs($this->member(OrgRoles::OWNER))->get('/dashboard')->assertOk();

        $this->assertSame('Review 2 approvals', $this->primary($response)[1]);
        $response->assertSee('2 actions are held until a person answers.');
        $this->assertSame('4', $this->figure($response, 'finished'));
        $this->assertSame('75%', $this->figure($response, 'rate'));
        $this->assertSame('1.10', $this->figure($response, 'cost'));
        $this->assertSame('2 min', $this->figure($response, 'duration'));
    }

    public function test_the_run_drawn_on_the_landing_is_a_real_one_with_its_own_steps(): void
    {
        $agent = $this->agent('Quote follow-up');
        $this->work($agent, AgentRun::COMPLETED, [[AgentRunStep::SYSTEM, 'An older step']], ['summary' => 'Older work']);
        $this->hold($agent);

        $response = $this->actingAs($this->member(OrgRoles::OWNER))->get('/dashboard')->assertOk();

        $response->assertSee('data-featured-run', false)->assertSee('The run that is waiting');
        $response->assertSeeInOrder(['Started from manual', 'Waiting for a person', 'Open this run']);
        $response->assertSee('stop-point held', false);
        $response->assertDontSee('An older step');
    }

    public function test_another_businesss_work_never_reaches_the_landing(): void
    {
        TenantContext::system(function () {
            $theirs = AgentDeployment::factory()->create(['status' => 'active']);
            AgentRun::create(['organization_id' => $theirs->organization_id, 'agent_deployment_id' => $theirs->id, 'config_version_id' => $theirs->published_version_id, 'trigger_type' => 'manual', 'status' => 'completed', 'summary' => 'Their private work', 'input' => 'x', 'limits' => [], 'finished_at' => now()]);
            AgentApproval::factory()->create(['organization_id' => $theirs->organization_id, 'agent_deployment_id' => $theirs->id, 'status' => 'pending', 'title' => 'Their private approval']);
        });

        $response = $this->actingAs($this->member(OrgRoles::OWNER))->get('/dashboard')->assertOk();

        $response->assertDontSee('Their private work')->assertDontSee('Their private approval')->assertSee('No run yet.');
        $this->assertSame('first-agent', $this->primary($response)[0]);
    }

    public function test_the_platform_list_holds_only_platforms_that_exist(): void
    {
        $this->assertEqualsCanonicalizing(self::PLATFORMS, array_keys(config('ecosystem.platforms')));
        $this->assertSame('Dot.Agents', config('ecosystem.platforms.agents.name'));
    }
}
