<?php

namespace Tests\Feature;

use App\Livewire\Agents\DeploymentManager;
use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as OutgoingRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The header carries the shared Dot UI (dot-ui): the platform switcher, and the in-page filters use its compact filter.
 * Dot.Agents has no header search, so none is added. The switcher only ever shows what InfoDot says the CURRENT
 * business holds, linking through InfoDot's /go (never straight to a host, never with a token).
 */
class DotUiHeaderTest extends TestCase
{
    use RefreshDatabase;

    private const REDEEM = 'https://infodot.test/api/ecosystem/redeem';

    private const ACME = '11111111-1111-4111-8111-111111111111';

    private const GLOBEX = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ecosystem.infodot' => ['redeem_url' => self::REDEEM, 'platform' => 'agents', 'secret' => 'a-test-secret', 'timeout' => 2, 'provision' => true, 'entitlement_check' => true],
            'ecosystem.platforms.infodot.url' => 'https://infodot.test',
        ]);
    }

    private static function url(string $uuid): string
    {
        return 'https://infodot.test/api/ecosystem/companies/'.$uuid.'/platforms*';
    }

    /** InfoDot's read endpoint, as it answers this platform about one company. */
    private static function body(string $uuid, array $held = ['agents', 'files', 'infodot']): array
    {
        $rows = [];
        foreach (['agents' => 'Dot.Agents', 'files' => 'Dot.Files', 'finance' => 'Dot.Finance', 'hr' => 'Dot.HR', 'infodot' => 'InfoDot', 'press' => 'Dot.Press'] as $key => $name) {
            $rows[] = ['key' => $key, 'name' => $name, 'status' => 'active', 'entitled' => in_array($key, $held, true), 'is_core' => false, 'access_mode' => 'everyone'];
        }

        return ['platform' => 'agents', 'company' => ['uuid' => $uuid, 'personal' => false], 'platforms' => $rows];
    }

    private function fakeInfoDot(array $stubs): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($stubs);
    }

    /** @return array{0: User, 1: Organization} a person who owns a business linked to InfoDot by uuid (personal when null) */
    private function memberOf(?string $uuid, string $name): array
    {
        $user = User::factory()->create(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $organization = Organization::factory()->create(['name' => $name, 'infodot_team_uuid' => $uuid]);
        $organization->users()->attach($user->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);

        return [$user, $organization];
    }

    private function open(User $user, Organization $organization): static
    {
        return $this->actingAs($user)->withSession(['current_organization_id' => $organization->id]);
    }

    /** The markup of the switcher only, so the assertions cannot be satisfied by the rest of the page. */
    private function switcherHtml(string $html): string
    {
        $this->assertSame(1, preg_match('/<div class="dsw dsw--header".*?<p class="dsw-sr" data-dsw-live[^>]*><\/p>\s*<\/div>\s*<\/div>\s*<\/div>/s', $html, $m), 'the header carries the switcher');

        return $m[0];
    }

    // -- No header search is invented -------------------------------------------------

    public function test_there_is_no_header_search_pill_because_the_platform_has_no_header_search(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        [$user, $organization] = $this->memberOf(self::ACME, 'Acme Ltd');

        $html = $this->open($user, $organization)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('dsr-input', $html);
        $this->assertStringNotContainsString('role="search"', $html);
    }

    public function test_a_guest_sees_no_switcher(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('data-dsw', false);
    }

    // -- The in-page filters --------------------------------------------------------

    public function test_the_agent_list_filter_is_the_shared_compact_filter_with_the_same_property(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        [$user, $organization] = $this->memberOf(self::ACME, 'Acme Ltd');
        Gate::before(fn () => true);

        $html = $this->open($user, $organization)->get(route('agents.deployments'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*\bclass="dsr-filter"[^>]*>/', $html);
        $this->assertStringContainsString('aria-label="Search agents"', $html);
        $this->assertStringContainsString('wire:model.live.debounce.300ms="search"', $html);
        $this->assertStringContainsString('id="dsr-filter-search"', $html);
    }

    public function test_the_audit_log_filter_is_the_shared_compact_filter_with_the_same_property(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        [$user, $organization] = $this->memberOf(self::ACME, 'Acme Ltd');
        Gate::before(fn () => true);

        $html = $this->open($user, $organization)->get(route('governance.audit'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*\bclass="dsr-filter"[^>]*>/', $html);
        $this->assertStringContainsString('aria-label="Search logs"', $html);
        $this->assertStringContainsString('wire:model.live.debounce.300ms="search"', $html);
    }

    public function test_the_agent_filter_still_finds_by_name_and_never_shows_another_business(): void
    {
        $this->fakeInfoDot([]);
        [$user, $organization] = $this->memberOf(null, 'Acme Ltd');
        $other = Organization::factory()->create();
        Gate::before(fn () => true);
        session(['current_organization_id' => $organization->id]);

        AgentDeployment::factory()->create(['organization_id' => $organization->id, 'name' => 'Alpha helper']);
        AgentDeployment::factory()->create(['organization_id' => $organization->id, 'name' => 'Beta helper']);
        AgentDeployment::factory()->create(['organization_id' => $other->id, 'name' => 'Alpha secret']);

        Livewire::actingAs($user)->test(DeploymentManager::class)
            ->set('search', 'Alpha')
            ->assertSee('Alpha helper')
            ->assertDontSee('Beta helper')
            ->assertDontSee('Alpha secret');
    }

    // -- The switcher ---------------------------------------------------------------

    public function test_a_member_sees_the_switcher_with_what_their_business_holds_and_the_current_platform_marked(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        [$user, $organization] = $this->memberOf(self::ACME, 'Acme Ltd');

        $html = $this->open($user, $organization)->get(route('dashboard'))->assertOk()->getContent();
        $panel = $this->switcherHtml($html);

        $this->assertStringContainsString('data-state="ok"', $panel);
        $this->assertStringContainsString('data-platform="agents"', $panel);
        $this->assertStringContainsString('Acme Ltd', $panel, 'the sentence names the business');

        $this->assertSame(1, preg_match('/<div class="dsw-bay is-current" aria-current="page">.*?You are here/s', $panel, $here));
        $this->assertStringContainsString('Dot.Agents', $here[0]);

        $this->assertStringContainsString('href="https://infodot.test/go/files?company='.self::ACME.'"', $panel);
        $this->assertStringContainsString('href="https://infodot.test/home"', $panel);
        $this->assertStringNotContainsString('/go/hr', $panel);
        $this->assertStringNotContainsString('/go/press', $panel);
        $this->assertStringContainsString('href="https://infodot.test/platforms/hr"', $panel);
        $this->assertStringContainsString('Not switched on (3)', $panel);

        $this->assertStringNotContainsString('token', strtolower($panel));
        $this->assertStringNotContainsString('a-test-secret', $html);
        preg_match_all('/href="([^"]+)"/', $panel, $hrefs);
        foreach ($hrefs[1] as $href) {
            $this->assertStringStartsWith('https://infodot.test/', $href, 'every way out of the switcher goes through InfoDot');
        }
    }

    public function test_the_switcher_follows_the_business_that_is_open_not_another_one_the_person_belongs_to(): void
    {
        $this->fakeInfoDot([
            self::url(self::ACME) => Http::response(self::body(self::ACME, ['agents', 'infodot', 'press'])),
            self::url(self::GLOBEX) => Http::response(self::body(self::GLOBEX, ['agents', 'infodot', 'hr'])),
        ]);
        [$user, $acme] = $this->memberOf(self::ACME, 'Acme Ltd');
        $globex = Organization::factory()->create(['name' => 'Globex Ltd', 'infodot_team_uuid' => self::GLOBEX]);
        $globex->users()->attach($user->id, ['role' => 'owner', 'is_primary' => false, 'joined_at' => now()]);

        $inAcme = $this->switcherHtml($this->open($user, $acme)->get(route('dashboard'))->getContent());
        $inGlobex = $this->switcherHtml($this->open($user, $globex)->get(route('dashboard'))->getContent());

        $this->assertStringContainsString('/go/press?company='.self::ACME, $inAcme);
        $this->assertStringNotContainsString(self::GLOBEX, $inAcme);
        $this->assertStringContainsString('/go/hr?company='.self::GLOBEX, $inGlobex);
        $this->assertStringNotContainsString(self::ACME, $inGlobex);
    }

    public function test_a_business_the_person_does_not_belong_to_is_never_asked_about(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        [$user, $acme] = $this->memberOf(self::ACME, 'Acme Ltd');
        Organization::factory()->create(['name' => 'Globex Ltd', 'infodot_team_uuid' => self::GLOBEX]);

        $this->open($user, $acme)->get(route('dashboard'))->assertOk();

        Http::assertSent(fn (OutgoingRequest $request) => str_contains($request->url(), '/companies/'.self::ACME.'/platforms')
            && $request->hasHeader('Authorization', 'Bearer a-test-secret')
            && $request->hasHeader('X-Dot-Platform', 'agents'));
        Http::assertNotSent(fn (OutgoingRequest $request) => str_contains($request->url(), self::GLOBEX));
    }

    public function test_when_infodot_cannot_be_reached_the_switcher_says_so_and_never_shows_active(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response('down', 503)]);
        [$user, $organization] = $this->memberOf(self::ACME, 'Acme Ltd');

        $html = $this->open($user, $organization)->get(route('dashboard'))->assertOk()->getContent();
        $panel = $this->switcherHtml($html);

        $this->assertStringContainsString('data-state="degraded"', $panel);
        $this->assertStringContainsString('Could not check your platforms just now', $panel);
        $this->assertStringNotContainsString('Active', $panel);
        $this->assertStringContainsString('/go/files', $panel);
        preg_match_all('/href="([^"]+)"/', $panel, $hrefs);
        foreach ($hrefs[1] as $href) {
            $this->assertStringStartsWith('https://infodot.test/', $href);
        }
    }

    public function test_a_connection_failure_never_breaks_the_page(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => fn () => throw new ConnectionException('refused')]);
        [$user, $organization] = $this->memberOf(self::ACME, 'Acme Ltd');

        $html = $this->open($user, $organization)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-state="degraded"', $html);
    }

    public function test_a_personal_workspace_gets_the_honest_sentence_and_infodot_is_not_asked(): void
    {
        $this->fakeInfoDot([]);
        [$user, $organization] = $this->memberOf(null, 'Sipho (personal)');

        $html = $this->open($user, $organization)->get(route('dashboard'))->assertOk()->getContent();
        $panel = $this->switcherHtml($html);

        $this->assertStringContainsString('data-state="personal"', $panel);
        $this->assertStringNotContainsString('dsw-bay', $panel);
        $this->assertStringContainsString('href="https://infodot.test/platforms"', $panel, 'the way to InfoDot is kept');
        Http::assertNothingSent();
    }

    public function test_the_dashboard_no_longer_has_the_two_link_back_nav_because_the_switcher_replaces_it(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        [$user, $organization] = $this->memberOf(self::ACME, 'Acme Ltd');

        $html = $this->open($user, $organization)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('arrive-back', $html);
        $this->assertStringContainsString('data-dsw', $html);
        $this->assertStringContainsString('href="https://infodot.test/home"', $html, 'InfoDot stays one click away, from the switcher');
        $this->assertStringContainsString('See all platforms', $html);
    }

    public function test_the_switcher_is_also_in_the_older_account_pages_layout(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        [$user, $organization] = $this->memberOf(self::ACME, 'Acme Ltd');

        $html = $this->open($user, $organization)->get(route('teams.create'))->assertOk()->getContent();

        $this->assertStringContainsString('class="topbar"', $html, 'the account pages use the older layout');
        $this->assertStringContainsString('/go/files?company='.self::ACME, $this->switcherHtml($html));
    }

    // -- CSP: self-hosted, nothing inline, nothing external ----------------------------

    public function test_the_shared_assets_are_served_from_this_site_and_resolve(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        [$user, $organization] = $this->memberOf(self::ACME, 'Acme Ltd');

        $response = $this->open($user, $organization)->get(route('dashboard'))->assertOk();
        $html = $response->getContent();

        foreach (['css/dot-fonts.css', 'css/dot-tokens.css', 'css/dot-search.css', 'css/dot-switcher-fonts.css', 'css/dot-switcher.css', 'js/dot-switcher.js'] as $asset) {
            $this->assertStringContainsString(asset('dot-ui/'.$asset), $html, $asset.' is linked from this site');
            $this->assertFileExists(public_path('dot-ui/'.$asset));
        }

        foreach (glob(public_path('dot-ui/css/*.css')) as $css) {
            preg_match_all('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/', (string) file_get_contents($css), $urls);
            foreach ($urls[1] as $url) {
                $this->assertDoesNotMatchRegularExpression('#^(https?:)?//#', $url, basename($css).' reaches outside this site');
                $this->assertFileExists(dirname($css).'/'.$url, basename($css).' -> '.$url);
            }
        }

        preg_match_all('/<img src="([^"]+)" alt="Dot\./', $this->switcherHtml($html), $imgs);
        $this->assertNotEmpty($imgs[1]);
        foreach ($imgs[1] as $src) {
            $this->assertStringStartsWith('/dot-ui/img/platforms/', $src);
            $this->assertFileExists(public_path($src));
        }
    }

    public function test_the_shared_markup_has_no_inline_style_or_script_and_the_policy_gained_no_origin(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        [$user, $organization] = $this->memberOf(self::ACME, 'Acme Ltd');
        Gate::before(fn () => true);

        $response = $this->open($user, $organization)->get(route('agents.deployments'))->assertOk();
        $html = $response->getContent();

        $fragment = $this->switcherHtml($html);
        $this->assertStringNotContainsString(' style=', $fragment);
        $this->assertStringNotContainsString('<style', $fragment);
        $this->assertStringNotContainsString('<script', $fragment);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+=/', $fragment, 'no inline event handlers');

        preg_match('/<input[^>]*\bclass="dsr-filter"[^>]*>/', $html, $filter);
        $this->assertStringNotContainsString(' style=', $filter[0]);

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('infodot.test', $policy, 'the switcher never calls InfoDot from the browser');
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringContainsString("img-src 'self'", $policy);
    }
}
