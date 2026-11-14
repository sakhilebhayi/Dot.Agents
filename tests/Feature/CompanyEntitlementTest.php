<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyEntitled;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Services\InfoDot\EntitlementClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-50: Dot.Agents honours the company's entitlement. At sign-in only companies InfoDot marks entitled are admitted;
 * during a session the signed-in area asks InfoDot whether the open business still holds the platform. A clear "no"
 * is the plain 403 page; every failure lets the person carry on. Bodies follow InfoDot's own tests.
 */
class CompanyEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private const REDEEM = 'https://infodot.test/api/ecosystem/redeem';

    private const ACME = '11111111-1111-4111-8111-111111111111';

    private const GLOBEX = '22222222-2222-4222-8222-222222222222';

    private string $credential;

    protected function setUp(): void
    {
        parent::setUp();

        $this->credential = Str::random(40);

        config(['ecosystem.infodot' => [
            'redeem_url' => self::REDEEM, 'platform' => 'agents', 'secret' => $this->credential, 'timeout' => 2, 'provision' => true,
            'entitlement_check' => true,
        ]]);
        config(['ecosystem.platforms.infodot.url' => 'https://infodot.test']);
    }

    // ------------------------------------------------------------------ helpers

    private static function readUrl(string $uuid): string
    {
        return 'https://infodot.test/api/ecosystem/companies/'.$uuid.'/platforms*';
    }

    private static function platforms(string $uuid, bool $entitled, string $status): array
    {
        return [
            'platform' => 'agents',
            'company' => ['uuid' => $uuid, 'personal' => false],
            'platforms' => [
                ['key' => 'agents', 'name' => 'Dot.Agents', 'status' => $status, 'entitled' => $entitled, 'is_core' => false, 'access_mode' => 'everyone'],
                ['key' => 'finance', 'name' => 'Dot.Finance', 'status' => 'included', 'entitled' => true, 'is_core' => true],
                ['key' => 'infodot', 'name' => 'InfoDot', 'status' => 'included', 'entitled' => true, 'is_core' => true],
            ],
        ];
    }

    private function infodotSays(array $stubs): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($stubs);
    }

    /** A signed-in person who owns a business here, linked to InfoDot by uuid (or personal when uuid is null). */
    private function member(?string $uuid = self::ACME, string $name = 'Acme Ltd', ?User $user = null): array
    {
        $user ??= User::factory()->create(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $organization = Organization::factory()->create(['name' => $name, 'infodot_team_uuid' => $uuid]);
        $organization->users()->attach($user->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);

        return [$user, $organization];
    }

    private function open(User $user, Organization $organization): static
    {
        return $this->actingAs($user)->withSession(['current_organization_id' => $organization->id]);
    }

    private function fromInfodot(): array
    {
        return ['Origin' => 'https://infodot.test'];
    }

    private function redeemTeam(string $uuid, string $name, string $role, ?bool $entitled = null, ?string $access = null, bool $personal = false): array
    {
        $team = ['id' => random_int(1, 999), 'uuid' => $uuid, 'name' => $name, 'personal' => $personal, 'role' => $role];

        return $entitled === null ? $team : $team + ['entitled' => $entitled, 'access' => $access];
    }

    private function signIn(string $email, array $teams, ?string $current = null)
    {
        $this->infodotSays([self::REDEEM => Http::response([
            'platform' => 'agents',
            'user' => ['id' => 1, 'uuid' => (string) Str::uuid(), 'name' => ucfirst(strstr($email, '@', true)), 'email' => $email, 'email_verified' => true],
            'teams' => $teams,
            'current_team_id' => 1,
            'current_team_uuid' => $current,
        ])]);

        auth()->logout();

        return $this->post(route('auth.ecosystem'), ['token' => 'one-time'], $this->fromInfodot());
    }

    // ------------------------------------------------------------------ sign-in (redeem)

    public function test_a_company_marked_entitled_is_admitted_at_sign_in(): void
    {
        $this->signIn('ann@acme.test', [$this->redeemTeam(self::ACME, 'Acme Ltd', 'owner', true)], self::ACME)->assertRedirect(route('dashboard'));

        $user = User::where('email', 'ann@acme.test')->firstOrFail();
        $organization = Organization::where('infodot_team_uuid', self::ACME)->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame($organization->id, session('current_organization_id'));
        $this->assertNotNull(Team::where('infodot_team_uuid', self::ACME)->first());
    }

    public function test_a_company_marked_not_entitled_is_not_created_updated_or_made_current(): void
    {
        // Globex exists here already under an older name, with Ann as a viewer working in it.
        $ann = User::factory()->create(['email' => 'ann@acme.test']);
        [, $globex] = $this->member(self::GLOBEX, 'Globex (old name)', $ann);
        $globex->users()->updateExistingPivot($ann->id, ['role' => 'viewer']);

        $this->signIn('ann@acme.test', [
            $this->redeemTeam(self::ACME, 'Acme Ltd', 'owner', true),
            $this->redeemTeam(self::GLOBEX, 'Globex Corporation', 'admin', false, 'suspended'),
            $this->redeemTeam('33333333-3333-4333-8333-333333333333', 'Initech', 'owner', false, 'not_activated'),
        ], self::GLOBEX)->assertRedirect(route('dashboard'));

        $acme = Organization::where('infodot_team_uuid', self::ACME)->firstOrFail();

        $this->assertNull(Organization::where('infodot_team_uuid', '33333333-3333-4333-8333-333333333333')->first(), 'a company that does not hold the platform is not created');
        $this->assertNull(Team::where('infodot_team_uuid', '33333333-3333-4333-8333-333333333333')->first());
        $this->assertSame('Globex (old name)', $globex->fresh()->name, 'nor renamed');
        $this->assertSame('viewer', $globex->users()->where('users.id', $ann->id)->first()->pivot->role, 'nor is the role changed');
        $this->assertTrue($ann->organizations()->where('organizations.id', $globex->id)->exists(), 'membership is left as it was, not revoked');
        $this->assertSame($acme->id, session('current_organization_id'), 'and it cannot be the open business');
    }

    public function test_without_the_key_sign_in_behaves_as_before(): void
    {
        $this->signIn('ann@acme.test', [
            $this->redeemTeam(self::ACME, 'Acme Ltd', 'owner'),
            $this->redeemTeam(self::GLOBEX, 'Globex', 'admin'),
        ], self::GLOBEX)->assertRedirect(route('dashboard'));

        $this->assertNotNull(Organization::where('infodot_team_uuid', self::ACME)->first());
        $this->assertSame(Organization::where('infodot_team_uuid', self::GLOBEX)->firstOrFail()->id, session('current_organization_id'));
    }

    public function test_when_no_company_is_entitled_the_person_gets_the_plain_page_and_is_not_signed_in(): void
    {
        $response = $this->signIn('ann@acme.test', [
            $this->redeemTeam('44444444-4444-4444-8444-444444444444', "Ann's Team", 'owner', false, 'no_personal_access', personal: true),
            $this->redeemTeam(self::ACME, 'Acme Ltd', 'owner', false, 'payment_required'),
        ], self::ACME);

        $response->assertStatus(403)
            ->assertSee('Dot.Agents needs a payment for Acme Ltd')
            ->assertSee('href="https://infodot.test/platforms/agents"', false)
            ->assertSee('Open Platforms in InfoDot')
            ->assertDontSee('Whoops')
            ->assertDontSee('Stack trace');

        $this->assertSame(1, substr_count($response->getContent(), 'data-primary-action'));
        $this->assertGuest();
        $this->assertNull(User::where('email', 'ann@acme.test')->first(), 'no account is created for nothing');
        $this->assertSame(0, Organization::whereNotNull('infodot_team_uuid')->count());
    }

    public function test_the_page_escapes_the_company_name(): void
    {
        $this->signIn('ann@acme.test', [$this->redeemTeam(self::ACME, '<script>alert(1)</script>', 'owner', false, 'not_activated')], self::ACME)
            ->assertStatus(403)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // ------------------------------------------------------------------ during a session

    public function test_an_entitled_company_carries_on(): void
    {
        [$user, $acme] = $this->member();
        $this->infodotSays([self::readUrl(self::ACME) => Http::response(self::platforms(self::ACME, true, 'active'))]);

        $this->open($user, $acme)->get(route('dashboard'))->assertOk();

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer '.$this->credential)
                && $request->hasHeader('X-Dot-Platform', 'agents')
                && str_contains($request->url(), 'platform=agents');
        });
    }

    #[DataProvider('refusals')]
    public function test_a_refused_company_gets_the_plain_403_with_no_data(string $status, string $title): void
    {
        [$user, $acme] = $this->member();
        $this->infodotSays([self::readUrl(self::ACME) => Http::response(self::platforms(self::ACME, false, $status))]);

        $response = $this->open($user, $acme)->get(route('dashboard'));

        $response->assertStatus(403)
            ->assertSee($title)
            ->assertSee('href="https://infodot.test/platforms/agents"', false)
            ->assertDontSee('Whoops')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(1, substr_count($response->getContent(), 'data-primary-action'));
        $this->assertSame($acme->name, $acme->fresh()->name, 'nothing is changed');
    }

    public static function refusals(): array
    {
        return [
            'suspended' => ['suspended', 'Dot.Agents is suspended for Acme Ltd'],
            'payment needed' => ['payment_required', 'Dot.Agents needs a payment for Acme Ltd'],
            'ended' => ['subscription_expired', 'Dot.Agents has ended for Acme Ltd'],
            'not switched on' => ['not_activated', 'Dot.Agents is not switched on for Acme Ltd'],
        ];
    }

    public function test_the_refusal_says_running_agents_are_left_alone(): void
    {
        [$user, $acme] = $this->member();
        $this->infodotSays([self::readUrl(self::ACME) => Http::response(self::platforms(self::ACME, false, 'subscription_expired'))]);

        $this->open($user, $acme)->get(route('dashboard'))->assertStatus(403)->assertSee('Agents, schedules and runs are not stopped or deleted');
    }

    public static function failures(): array
    {
        $own = fn (array $row) => ['platform' => 'agents', 'company' => ['uuid' => self::ACME, 'personal' => false], 'platforms' => [$row]];

        return [
            '401' => [fn () => Http::response(['message' => 'no'], 401)],
            '403' => [fn () => Http::response(['message' => 'off'], 403)],
            '404' => [fn () => Http::response(['message' => 'unknown'], 404)],
            '500' => [fn () => Http::response('boom', 500)],
            'timeout' => [fn () => fn () => throw new ConnectionException('timed out')],
            'not json' => [fn () => Http::response('<html>', 200)],
            'wrong platform' => [fn () => Http::response(['platform' => 'forms', 'platforms' => []], 200)],
            'own row missing' => [fn () => Http::response(['platform' => 'agents', 'platforms' => [['key' => 'finance', 'entitled' => false]]], 200)],
            'entitled not a bool' => [fn () => Http::response($own(['key' => 'agents', 'entitled' => 'no', 'status' => 'x']), 200)],
        ];
    }

    #[DataProvider('failures')]
    public function test_every_failure_lets_the_person_carry_on_and_is_logged_once(\Closure $stub): void
    {
        [$user, $acme] = $this->member();
        $this->infodotSays([self::readUrl(self::ACME) => $stub()]);
        Log::spy();

        $this->open($user, $acme)->get(route('dashboard'))->assertOk();
        $this->get(route('dashboard'))->assertOk();

        // The second request is served from the failure cache: InfoDot was asked, and the failure logged, once.
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_missing_secret_carries_on_without_asking(): void
    {
        config(['ecosystem.infodot.secret' => '']);
        [$user, $acme] = $this->member();
        $this->infodotSays([]);

        $this->open($user, $acme)->get(route('dashboard'))->assertOk();

        Http::assertNothingSent();
    }

    public function test_the_answer_is_cached_per_company(): void
    {
        [$user, $acme] = $this->member();
        $this->infodotSays([self::readUrl(self::ACME) => Http::response(self::platforms(self::ACME, true, 'active'))]);

        $this->open($user, $acme)->get(route('dashboard'))->assertOk();
        $this->get(route('dashboard'))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_the_flag_off_makes_no_call(): void
    {
        config(['ecosystem.infodot.entitlement_check' => false]);
        [$user, $acme] = $this->member();
        $this->infodotSays([]);

        $this->open($user, $acme)->get(route('dashboard'))->assertOk();

        Http::assertNothingSent();
    }

    public function test_exempt_routes_make_no_call(): void
    {
        [$user] = $this->member();
        $this->infodotSays([]);

        $this->get('/')->assertOk();
        $this->get('/login')->assertStatus(200);
        $this->get('/up')->assertOk();
        $this->get('/ready');
        $this->get('/auth/ecosystem')->assertRedirect('/login');
        $this->post('/webhooks/stripe', [])->status();
        $this->get('/terms')->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_bearer_token_request_is_machine_to_machine_and_not_checked(): void
    {
        [$user, $acme] = $this->member();
        $this->infodotSays([]);

        $this->open($user, $acme)->withHeader('Authorization', 'Bearer '.Str::random(20))->get(route('dashboard'));

        Http::assertNothingSent();
    }

    public function test_a_second_company_that_holds_the_platform_can_be_switched_to(): void
    {
        [$user, $acme] = $this->member(self::ACME, 'Acme Ltd');
        [, $globex] = $this->member(self::GLOBEX, 'Globex Corp', $user);
        $this->infodotSays([
            self::readUrl(self::ACME) => Http::response(self::platforms(self::ACME, false, 'suspended')),
            self::readUrl(self::GLOBEX) => Http::response(self::platforms(self::GLOBEX, true, 'active')),
        ]);

        $page = $this->open($user, $acme)->get(route('dashboard'));
        $page->assertStatus(403)->assertSee('Globex Corp')->assertSee(route('organization.switch'), false);

        $this->post(route('organization.switch'), ['organization_id' => $globex->id])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_switching_to_a_business_the_person_is_not_in_is_forbidden(): void
    {
        [$user, $acme] = $this->member();
        $stranger = Organization::factory()->create(['infodot_team_uuid' => self::GLOBEX]);
        $this->infodotSays([]);

        $this->open($user, $acme)->post(route('organization.switch'), ['organization_id' => $stranger->id])->assertForbidden();
    }

    // ------------------------------------------------------------------ personal workspaces

    private function signInThenWorkPersonally(?bool $personalEntitled): array
    {
        $teams = [$this->redeemTeam('55555555-5555-4555-8555-555555555555', "Ann's Team", 'owner', $personalEntitled, $personalEntitled === false ? 'no_personal_access' : null, personal: true)];
        if ($personalEntitled === null) {
            $teams = [$this->redeemTeam('55555555-5555-4555-8555-555555555555', "Ann's Team", 'owner', personal: true)];
        }
        // A company that holds the platform keeps the sign-in alive; the person then works in their personal business.
        $teams[] = $this->redeemTeam(self::ACME, 'Acme Ltd', 'owner', true);

        $ann = User::factory()->create(['email' => 'ann@acme.test', 'consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $this->signIn('ann@acme.test', $teams, null)->assertRedirect(route('dashboard'));

        $personal = Organization::factory()->create(['name' => "Ann's Organization", 'infodot_team_uuid' => null]);
        $personal->users()->attach($ann->id, ['role' => 'owner', 'is_primary' => false, 'joined_at' => now()]);
        session(['current_organization_id' => $personal->id]);
        $this->infodotSays([self::readUrl(self::ACME) => Http::response(self::platforms(self::ACME, true, 'active'))]);

        return [$ann, $personal];
    }

    public function test_a_personal_workspace_is_refused_when_redeem_said_false(): void
    {
        [$ann, $personal] = $this->signInThenWorkPersonally(false);

        $this->assertFalse(session(EnsureCompanyEntitled::PERSONAL));

        $this->get(route('dashboard'))
            ->assertStatus(403)
            ->assertSee('Dot.Agents needs a company that has switched it on')
            ->assertSee('href="https://infodot.test/platforms/agents"', false)
            ->assertSee('Acme Ltd');

        Http::assertNothingSent();
        $this->assertSame("Ann's Organization", $personal->fresh()->name, 'nothing is altered');
    }

    public function test_a_personal_workspace_carries_on_when_redeem_said_true(): void
    {
        $this->signInThenWorkPersonally(true);

        $this->assertTrue(session(EnsureCompanyEntitled::PERSONAL));
        $this->get(route('dashboard'))->assertOk();
        Http::assertNothingSent();
    }

    public function test_a_personal_workspace_carries_on_when_redeem_said_nothing(): void
    {
        $this->signInThenWorkPersonally(null);

        $this->assertFalse(session()->has(EnsureCompanyEntitled::PERSONAL));
        $this->get(route('dashboard'))->assertOk();
        Http::assertNothingSent();
    }

    public function test_the_personal_refusal_is_off_with_the_flag(): void
    {
        $this->signInThenWorkPersonally(false);
        config(['ecosystem.infodot.entitlement_check' => false]);

        $this->get(route('dashboard'))->assertOk();
    }

    // ------------------------------------------------------------------ live updates

    private function livewireSnapshot(): string
    {
        $page = $this->get(route('org.settings'))->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $page, $all);
        $snapshot = '';
        foreach ($all[1] as $candidate) {
            $decoded = html_entity_decode($candidate, ENT_QUOTES);
            if (str_contains($decoded, 'organization-settings')) {
                $snapshot = $decoded;
            }
        }
        $this->assertNotSame('', $snapshot, 'the settings component is on the page');

        return $snapshot;
    }

    private function livewireRename(string $snapshot, string $name)
    {
        return $this->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot,
            'updates' => ['name' => $name],
            'calls' => [['path' => '', 'method' => 'save', 'params' => []]],
        ]]], ['X-Livewire' => 'true']);
    }

    public function test_a_livewire_update_is_refused_once_the_company_loses_the_platform(): void
    {
        [$user, $acme] = $this->member();
        $this->infodotSays([self::readUrl(self::ACME) => Http::sequence()
            ->push(self::platforms(self::ACME, true, 'active'))
            ->push(self::platforms(self::ACME, false, 'suspended'))]);
        $this->open($user, $acme);
        $snapshot = $this->livewireSnapshot();

        $worked = $this->livewireRename($snapshot, 'Acme Holdings');
        $this->assertNotSame(403, $worked->getStatusCode());
        $this->assertSame('Acme Holdings', $acme->fresh()->name, 'while the company holds the platform the action works');

        $this->travel(EntitlementClient::ANSWER_TTL + 1)->seconds();
        $response = $this->livewireRename($snapshot, 'Hijacked');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertArrayNotHasKey('components', (array) $response->json());
        $this->assertSame('Acme Holdings', $acme->fresh()->name, 'the data is unchanged');
    }

    public function test_livewire_updates_from_the_signed_in_area_carry_the_check(): void
    {
        $this->assertContains(EnsureCompanyEntitled::class, app(PersistentMiddleware::class)->getPersistentMiddleware());
    }
}
