<?php

namespace App\Providers;

use App\Models\AgentCategory;
use App\Models\AgentDepartment;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * Service bindings are handled by domain-scoped providers:
     *  - AgentServiceProvider    → AI / Agent runtime services
     *  - GovernanceServiceProvider → Governance, scoring, audit services
     *  - SocialServiceProvider   → Social Commerce & Resilience services
     */
    public function register(): void
    {
        // Intentionally empty — see domain providers above.
    }

    /**
     * Bootstrap any application services.
     * Policy registrations are handled by PolicyServiceProvider.
     */
    public function boot(): void
    {
        // Enforce strong password policy platform-wide (min 12 chars, mixed case, numbers, symbols)
        // In testing, use a relaxed rule so test factories and Jetstream tests still pass.
        Password::defaults(function () {
            if (app()->environment('testing')) {
                return Password::min(8);
            }

            return Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });

        // Enforce Tailwind CSS dark mode class strategy
        Blade::directive('mark', function ($text) {
            return "<?php echo e({$text}); ?>";
        });

        // Invalidate marketplace caches when departments or categories change
        AgentDepartment::saved(fn () => Cache::forget('marketplace_departments'));
        AgentDepartment::deleted(fn () => Cache::forget('marketplace_departments'));
        AgentCategory::saved(fn () => Cache::forget('marketplace_categories'));
        AgentCategory::deleted(fn () => Cache::forget('marketplace_categories'));

        // ── Named rate limiters ──────────────────────────────────────────────
        // Explicitly resolve via the sanctum guard so that API token requests
        // (which authenticate on the 'sanctum'/'api' guard, NOT the default
        // web guard) are keyed per-user rather than falling back to shared IP.

        // AI execution: 10 calls/minute per authenticated user
        RateLimiter::for('ai-execution', function (Request $request) {
            $userId = optional($request->user('sanctum') ?? $request->user())->id;
            $orgId = session('current_organization_id', 'anon');

            return [
                // Per-user: 10 AI skill executions per minute
                Limit::perMinute(10)->by('user:'.($userId ?: $request->ip())),
                // Per-org: 100 AI executions per minute (prevents one org from
                // monopolising shared GPU/LLM resources and controlling cost overruns)
                Limit::perMinute(100)->by('org:'.$orgId),
            ];
        });

        // Agent chat (Livewire): 20 messages/minute per user
        RateLimiter::for('agent-chat', function (Request $request) {
            $userId = optional($request->user())->id;

            return [
                Limit::perMinute(20)->by('chat-user:'.($userId ?: $request->ip())),
                Limit::perMinute(200)->by('chat-org:'.session('current_organization_id', 'anon')),
            ];
        });

        // Strict write operations: 30 creates/minute per authenticated user
        RateLimiter::for('api-writes', function (Request $request) {
            $userId = optional($request->user('sanctum') ?? $request->user())->id;

            return Limit::perMinute(30)->by($userId ?: $request->ip());
        });

        // General API: 200 requests/minute per authenticated user (org-level burst protection)
        RateLimiter::for('api', function (Request $request) {
            $userId = optional($request->user('sanctum') ?? $request->user())->id;

            return [
                Limit::perMinute(200)->by($userId ?: $request->ip()),
                // Org-level burst cap — prevents one org drowning shared resources
                Limit::perMinute(500)
                    ->by('org:'.session('current_organization_id', 'anon')),
            ];
        });
    }
}
