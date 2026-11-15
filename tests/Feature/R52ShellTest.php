<?php

namespace Tests\Feature;

use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\AgentWorkflow;
use App\Models\Organization;
use App\Models\PlatformNotification;
use App\Models\User;
use App\Support\OrgRoles;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-52: the one shared header over the Signal Box shell, Roboto everywhere, and the mobile menu that opens the platform's own sidebar.
 */
class R52ShellTest extends TestCase
{
    use RefreshDatabase;

    private Organization $acme;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = Organization::factory()->create(['name' => 'Acme Ltd']);
        $this->owner = User::factory()->create(['name' => 'Thandiwe Mokoena', 'consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $this->acme->users()->attach($this->owner->id, ['role' => OrgRoles::OWNER, 'is_primary' => true, 'joined_at' => now()]);
    }

    private function page(string $route, array $params = []): string
    {
        return $this->actingAs($this->owner)->withSession(['current_organization_id' => $this->acme->id])->get(route($route, $params))->assertOk()->getContent();
    }

    /** Every page type the signed-in shell renders, with the records they need. @return array<string, string> label => html */
    private function everyPageType(): array
    {
        $agent = TenantContext::system(fn () => AgentDeployment::factory()->create(['organization_id' => $this->acme->id, 'status' => 'active', 'name' => 'Invoice checker']));
        $run = TenantContext::system(fn () => AgentRun::create(['organization_id' => $this->acme->id, 'agent_deployment_id' => $agent->id, 'config_version_id' => $agent->published_version_id, 'trigger_type' => 'manual', 'status' => 'completed', 'summary' => 'Check', 'input' => 'x', 'limits' => [], 'finished_at' => now()]));
        $workflow = TenantContext::system(fn () => AgentWorkflow::factory()->create(['organization_id' => $this->acme->id]));

        $pages = [];
        foreach ([
            'dashboard' => ['dashboard', []], 'agents list' => ['agents.deployments', []], 'agent detail' => ['agents.show', [$agent]], 'agent setup' => ['agents.setup', [$agent]],
            'new agent' => ['agents.create', []], 'teams' => ['agents.teams', []], 'runs' => ['runs.index', []], 'run detail' => ['runs.show', [$run]], 'usage' => ['usage', []],
            'workflows' => ['workflows.index', []], 'builder' => ['workflows.builder', [$workflow]], 'approvals' => ['governance.approvals', []], 'audit' => ['governance.audit', []],
            'decisions' => ['governance.decisions', []], 'security' => ['security.center', []], 'members' => ['org.members', []], 'knowledge' => ['org.knowledge', []],
            'memory' => ['org.memory', []], 'skills' => ['org.skills', []], 'settings' => ['org.settings', []], 'billing' => ['billing.index', []], 'marketplace' => ['marketplace', []],
            'search' => ['search', []], 'profile' => ['profile.show', []], 'team settings (Jetstream)' => ['teams.create', []],
        ] as $label => [$route, $params]) {
            $pages[$label] = $this->page($route, $params);
        }

        return $pages;
    }

    // -- The header ----------------------------------------------------------------------------

    public function test_every_page_type_has_the_shared_header_with_a_real_search(): void
    {
        foreach ($this->everyPageType() as $label => $html) {
            $this->assertStringContainsString('<header class="dh', $html, $label);
            $this->assertSame(1, preg_match_all('/<header class="dh/', $html), $label.': exactly one header');
            $this->assertStringContainsString('class="dsr-input"', $html, $label.': search input');
            $this->assertStringContainsString('role="search"', $html, $label);
            $this->assertStringContainsString('data-dh-search-toggle', $html, $label.': the phone search button');
            $this->assertStringContainsString('data-dsw', $html, $label.': switcher');
            $this->assertStringContainsString('dh-account', $html, $label.': account menu');
            $this->assertStringContainsString('dh-bell--panel', $html, $label.': notifications bell');
        }
    }

    public function test_the_menu_button_opens_the_platforms_own_sidebar_and_nothing_is_duplicated(): void
    {
        $html = $this->page('dashboard');

        $this->assertStringContainsString('dh--adopt', $html);
        $this->assertStringContainsString('data-dh-menu-button', $html);
        $this->assertStringContainsString('aria-controls="app-sidebar"', $html);
        $this->assertStringContainsString('<aside class="side" id="app-sidebar"', $html);
        $this->assertMatchesRegularExpression('/<aside class="side" id="app-sidebar".*?<button type="button" class="icon-btn side-close" data-dh-close/s', $html, 'a close button inside the sidebar');
        $this->assertSame(1, substr_count($html, 'data-dh-menu-button'), 'one menu button');
        $this->assertStringNotContainsString('menu-btn', str_replace('dh-menu-btn', '', $html), 'the old hamburger that showed on desktop is gone');
        $this->assertStringNotContainsString('class="veil"', $html, 'the old scrim is gone; dot-ui draws one on <body>');
        $this->assertStringNotContainsString('id="dh-drawer"', $html, 'no second drawer');
        $this->assertStringContainsString('dh--nonav', $html);
        $this->assertStringContainsString('Skip to content', $html);
        $this->assertSame(18, substr_count($html, 'class="side-item"'), 'the sidebar keeps its 18 items');
    }

    public function test_the_notifications_bell_is_the_shared_panel_with_the_real_list_and_count(): void
    {
        PlatformNotification::factory()->count(2)->create(['organization_id' => $this->acme->id, 'user_id' => $this->owner->id, 'title' => 'Budget nearly used', 'read_at' => null]);

        $html = $this->page('dashboard');

        $this->assertStringContainsString('aria-label="Notifications, 2 unread"', $html);
        $this->assertStringContainsString('id="notifications-panel"', $html);
        $this->assertStringContainsString('Budget nearly used', $html);
        $this->assertStringContainsString('Mark all read', $html);
        $this->assertStringNotContainsString('>Notifications</button>', $html, 'no 110px text button in the bar');
    }

    public function test_the_account_menu_holds_sign_out_the_theme_and_switching_business(): void
    {
        $other = Organization::factory()->create(['name' => 'Globex Ltd']);
        $other->users()->attach($this->owner->id, ['role' => OrgRoles::VIEWER, 'is_primary' => false, 'joined_at' => now()]);

        $html = $this->page('dashboard');

        $this->assertStringContainsString('action="'.route('logout').'"', $html);
        $this->assertStringContainsString('data-dh-theme-set="light"', $html);
        $this->assertStringContainsString('Acme Ltd · Business owner', $html);
        $this->assertStringContainsString('Switch business', $html);
        $this->assertStringContainsString('action="'.route('organization.switch').'"', $html);
        $this->assertStringContainsString('value="'.$other->id.'"', $html);
        $this->assertStringContainsString('Globex Ltd', $html);
    }

    public function test_one_business_means_no_switch_rows(): void
    {
        $this->assertStringNotContainsString('Switch business', $this->page('dashboard'));
    }

    public function test_the_theme_is_set_before_first_paint_by_an_external_file(): void
    {
        $html = $this->page('dashboard');

        $this->assertStringContainsString('data-dh-theme-key="dot-agents-theme"', $html);
        $this->assertStringContainsString('data-dh-theme-default="dark"', $html);
        $this->assertStringContainsString('data-dh-theme-class="dark"', $html);
        $this->assertMatchesRegularExpression('#<script src="[^"]*/dot-ui/js/dot-theme-boot\.min\.js"></script>#', $html, 'synchronous, in <head>');
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'dot-theme-boot.min.js'));
    }

    // -- Roboto, self-hosted ----------------------------------------------------------------

    public function test_roboto_is_declared_and_its_files_are_served_from_this_origin(): void
    {
        $css = (string) file_get_contents(public_path('dot-ui/css/dot-roboto.css'));
        $this->assertMatchesRegularExpression("/@font-face\s*\{[^}]*font-family:\s*'Roboto'/", $css);

        preg_match_all('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/', $css, $urls);
        $this->assertNotEmpty($urls[1]);
        foreach ($urls[1] as $url) {
            $this->assertDoesNotMatchRegularExpression('#^(https?:)?//#', $url);
            $this->assertFileExists(public_path('dot-ui/css/'.$url));
        }

        $html = $this->page('dashboard');
        $this->assertStringContainsString('class="dark dot-roboto"', $html);
        // dot-ui 0.6.1: one bundle (Roboto, type scale, tokens, search, switcher, header), no font preload (it delayed a text LCP).
        $this->assertStringContainsString(asset('dot-ui/css/dot-ui.min.css'), $html);
        $this->assertStringNotContainsString('as="font"', $html);
    }

    public function test_no_page_or_stylesheet_reaches_a_font_host_or_names_another_text_face(): void
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[] = $file->getPathname();
        }
        foreach (glob(resource_path('css/*.css')) as $css) {
            $files[] = $css;
        }
        foreach (glob(resource_path('css/signal/*.css')) as $css) {
            $files[] = $css;
        }
        $files[] = base_path('tailwind.config.js');

        foreach ($files as $path) {
            $source = (string) file_get_contents($path);
            $this->assertStringNotContainsString('fonts.googleapis.com', $source, $path);
            $this->assertStringNotContainsString('fonts.gstatic.com', $source, $path);
            // email bodies need a web-safe stack, they are not a screen of this app
            if (! str_contains($path, '/emails/') && ! str_contains($path, '/vendor/mail/')) {
                $this->assertDoesNotMatchRegularExpression("/Public Sans|IBM Plex|Space Grotesk|'Inter'|Syne|JetBrains|Archivo|Big Shoulders/", $source, $path);
            }
        }
    }

    public function test_the_signal_box_type_tokens_are_the_shared_roboto_ones(): void
    {
        $signal = (string) file_get_contents(resource_path('css/signal.css'));

        $this->assertStringContainsString('--sans: var(--dot-font-sans);', $signal);
        $this->assertStringContainsString('--mono: var(--dot-font-mono);', $signal);
        $this->assertStringContainsString('presets: [dotPreset]', (string) file_get_contents(base_path('tailwind.config.js')));
    }

    public function test_the_policy_names_no_font_origin_any_more(): void
    {
        $csp = (string) $this->actingAs($this->owner)->withSession(['current_organization_id' => $this->acme->id])->get(route('dashboard'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("font-src 'self' data:", $csp);
        $this->assertStringNotContainsString('gstatic', $csp);
        $this->assertStringNotContainsString('googleapis', $csp);
    }

    public function test_the_public_and_sign_in_pages_use_roboto_from_this_site_too(): void
    {
        foreach (['/', route('login'), route('terms.show'), route('policy.show')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('class="dot-roboto"', $html, $url);
            $this->assertStringContainsString('/dot-ui/css/dot-roboto.min.css', $html, $url);
            $this->assertStringNotContainsString('googleapis', $html, $url);
        }
    }

    // -- CSP: nothing inline ----------------------------------------------------------------

    public function test_the_shell_has_no_inline_style_block_and_no_inline_script_of_its_own(): void
    {
        foreach (['dashboard' => $this->page('dashboard'), 'agents' => $this->page('agents.deployments'), 'search' => $this->page('search')] as $label => $html) {
            preg_match_all('#<style\b([^>]*)>#', $html, $styles, PREG_SET_ORDER);
            foreach ($styles as $style) {
                $this->assertStringContainsString('nonce=', $style[1], $label.': a style block must carry the CSP nonce (Livewire boot only)');
            }
            preg_match_all('#<script\b([^>]*)>(.*?)</script>#s', $html, $scripts, PREG_SET_ORDER);
            foreach ($scripts as $script) {
                if (! str_contains($script[1], 'src=')) {
                    $this->assertStringContainsString('nonce=', $script[1], $label.': an inline script must carry the CSP nonce (Livewire/Alpine boot only)');
                }
            }
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/', $html, $label.': no inline event handlers');
        }
    }

    public function test_the_banner_pages_carry_no_inline_style_in_the_layout_itself(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/platform.blade.php'));

        $this->assertStringNotContainsString(' style=', $layout);
        $this->assertStringNotContainsString('<style', $layout);
    }
}
