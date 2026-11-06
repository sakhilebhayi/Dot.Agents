<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\SchedulerProbeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HeadersAndReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_policy_is_enforced_with_a_nonce_and_no_open_image_or_script_source(): void
    {
        $policy = (string) $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+' 'unsafe-eval';/", $policy);
        $this->assertStringContainsString("script-src-attr 'none'", $policy);
        $this->assertStringContainsString("connect-src 'self';", $policy);
        $this->assertStringContainsString('report-uri /csp-report', $policy);
        $this->assertDoesNotMatchRegularExpression('/img-src[^;]* https:( |;)/', $policy);
    }

    public function test_the_policy_can_go_back_to_report_only_without_a_deploy(): void
    {
        config(['security.csp_report_only' => true]);

        $response = $this->get('/');

        $this->assertTrue($response->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_hsts_covers_this_host_alone_and_only_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $header = (string) $this->get('https://localhost/')->headers->get('Strict-Transport-Security');

        $this->assertStringContainsString('max-age=', $header);
        $this->assertStringNotContainsString('includeSubDomains', $header);
    }

    public function test_no_view_loads_a_script_from_a_cdn_or_uses_a_handler_attribute(): void
    {
        foreach (File::allFiles(resource_path('views')) as $view) {
            if (str_contains($view->getRelativePathname(), 'vendor')) {
                continue;
            }

            $source = $view->getContents();

            $this->assertDoesNotMatchRegularExpression('/<script[^>]+src="https?:/', $source, $view->getRelativePathname().' loads a script the policy blocks');
            $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit|load)\s*=\s*["\']/', $source, $view->getRelativePathname().' uses a handler the policy blocks');
        }
    }

    public function test_ready_reports_each_check_and_names_no_secret(): void
    {
        Cache::forever(SchedulerProbeController::SCHEDULER_KEY, now()->toIso8601String());

        $this->getJson('/ready')->assertOk()->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['status', 'checks' => ['database', 'cache', 'storage', 'scheduler'], 'checked_at']);
    }

    public function test_ready_fails_when_a_migration_is_pending(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);

        $this->getJson('/ready')->assertStatus(503)->assertJsonPath('status', 'fail')->assertJsonPath('checks.database.ok', false);
    }

    public function test_a_violation_report_keeps_only_the_directive_and_host(): void
    {
        Log::spy();

        $this->postJson('/csp-report', ['csp-report' => ['effective-directive' => 'img-src', 'blocked-uri' => 'https://evil.test/leak?secret=abc']])->assertNoContent();

        Log::shouldHaveReceived('info')->once()->with('csp.violation', ['directive' => 'img-src', 'blocked_host' => 'evil.test']);
    }
}
