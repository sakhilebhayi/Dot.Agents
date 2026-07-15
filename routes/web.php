<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\SocialAccountController;
use App\Http\Controllers\SocialOAuthController;
use App\Models\AgentDeployment;
use App\Models\AgentWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// ── Health Checks ─────────────────────────────────────────────────────────────
// Public ping — used by load balancers and uptime monitors
Route::get('/health', [HealthCheckController::class, 'ping'])->name('health.ping');

// Authenticated detailed health report — for internal monitoring dashboards
Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {
    Route::get('/health/detailed', [HealthCheckController::class, 'detailed'])->name('health.detailed');
});

// Landing page
Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : view('welcome');
});

// Consent routes — accessible to authenticated users regardless of consent status
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->prefix('consent')
    ->name('consent.')
    ->group(function () {
        Route::get('/', fn () => view('consent.show'))->name('show');
        Route::post('/', function () {
            /** @var User $user */
            $user = Auth::user();
            $records = $user->consent_records ?? [];
            $records['platform_terms'] = [
                'accepted_at' => now()->toIso8601String(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];
            $user->update(['consent_records' => $records]);

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Thank you for accepting the platform terms.');
        })->name('accept');
    });

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'consent.required',
    'org.context',
])->group(function () {

    // Dashboard
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Marketplace
    Route::get('/marketplace', fn () => view('marketplace.index'))->name('marketplace');

    // Active Agents (deployed workforce)
    Route::prefix('my-agents')->name('agents.')->group(function () {
        Route::get('/', fn () => view('agents.index'))->name('deployments');
        Route::get('/{deployment}', function (AgentDeployment $deployment) {
            abort_unless(Gate::allows('view', $deployment), 403);

            return view('agents.show', compact('deployment'));
        })->name('show');
        Route::get('/{deployment}/chat', function (AgentDeployment $deployment) {
            abort_unless(Gate::allows('chat', $deployment), 403);

            return view('agents.chat', compact('deployment'));
        })->name('chat')->middleware('throttle:agent-chat');
        Route::get('/{deployment}/scorecard', function (AgentDeployment $deployment) {
            abort_unless(Gate::allows('view', $deployment), 403);

            return view('agents.scorecard', compact('deployment'));
        })->name('scorecard');
    });

    // Workflows
    Route::prefix('workflows')->name('workflows.')->group(function () {
        Route::get('/', fn () => view('workflows.index'))->name('index');
        Route::get('/{workflow}/builder', function (AgentWorkflow $workflow) {
            abort_unless(
                $workflow->organization_id === session('current_organization_id'),
                403
            );

            return view('workflows.builder', compact('workflow'));
        })->name('builder');
    });

    // Governance
    Route::prefix('governance')->name('governance.')->group(function () {
        Route::get('/approvals', fn () => view('governance.approvals'))->name('approvals');
        Route::get('/audit', fn () => view('governance.audit'))->name('audit');
        Route::get('/decisions', fn () => view('governance.decisions'))->name('decisions');
    });

    // Security
    Route::get('/security', fn () => view('security.center'))->name('security.center');

    // Organization
    Route::prefix('organization')->name('org.')->group(function () {
        Route::get('/departments', fn () => view('org.departments'))->name('departments');
        Route::get('/members', fn () => view('org.members'))->name('members');
        Route::get('/knowledge', fn () => view('org.knowledge'))->name('knowledge');
        Route::get('/settings', fn () => view('org.settings'))->name('settings');
    });

    // Billing
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', fn () => view('billing.index'))->name('index');
        Route::get('/plans', fn () => view('billing.plans'))->name('plans');
        Route::get('/success', [BillingController::class, 'success'])->name('success');
        Route::get('/portal', [BillingController::class, 'portal'])->name('portal');
        Route::post('/checkout/{plan}', [BillingController::class, 'checkout'])->name('checkout');
        Route::get('/settings', fn () => view('billing.settings'))->name('settings.billing');
    });

    // Settings — canonical billing settings URL for Jetstream profile sidebar
    Route::get('/settings/billing', fn () => view('billing.settings'))->name('settings.billing.alt');

    // ── Social Commerce & Customer Success (SCCS) ─────────────────────────────
    Route::prefix('social')->name('social.')->group(function () {
        Route::get('/', fn () => view('social.dashboard'))->name('dashboard');
        Route::get('/inbox', fn () => view('social.inbox'))->name('inbox');
        Route::get('/leads', fn () => view('social.leads'))->name('leads');
        Route::get('/posts', fn () => view('social.posts'))->name('posts');
        Route::get('/sentiment', fn () => view('social.sentiment'))->name('sentiment');
        Route::get('/accounts', fn () => view('social.accounts'))->name('accounts');
        Route::get('/connect', fn () => view('social.connect'))->name('connect');
        Route::delete('/accounts/{socialAccount}', [SocialAccountController::class, 'destroy'])->name('accounts.destroy');

        // OAuth redirect + callback (platform = facebook|instagram|linkedin|twitter|tiktok)
        Route::get('/auth/{platform}/redirect', [SocialOAuthController::class, 'redirect'])->name('auth.redirect');
        Route::get('/auth/{platform}/callback', [SocialOAuthController::class, 'callback'])->name('auth.callback');
    });
});

// Stripe webhook — must be outside auth middleware + CSRF exempt (handled in VerifyCsrfToken)
Route::post('/webhooks/stripe', [BillingController::class, 'webhook'])->name('stripe.webhook');
