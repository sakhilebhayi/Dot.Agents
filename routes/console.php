<?php

use App\Jobs\GenerateMegaV2PlatformScorecard;
use App\Jobs\RunDigitalImmuneSystemCheck;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Scheduled Platform Jobs ──────────────────────────────────────────────────

// Digital Immune System — runs every 15 minutes, without overlapping, on one server
Schedule::job(new RunDigitalImmuneSystemCheck)
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->name('dis-health-check');

// Detect models with rows past their retention window and raise a
// RetentionPurgeProposal for platform_admin review — runs nightly at 02:00.
// Replaces a former unattended `model:prune` schedule that permanently
// deleted rows (including audit logs) with no human review; deletion now
// only happens when a platform_admin approves a proposal via the
// Retention Purge Queue (see ProcessRetentionPurgeAction).
Schedule::command('retention:detect-purge-candidates')
    ->dailyAt('02:00')
    ->onOneServer();

// Expire pending approvals past their deadline — runs every 30 minutes
Schedule::command('approvals:expire-overdue')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// MEGA V2 Autonomous Enterprise Readiness Scorecard — generated daily at 03:15
Schedule::job(new GenerateMegaV2PlatformScorecard)
    ->dailyAt('03:15')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->name('mega-v2-scorecard');

// DWCA — Digital Workforce Certification Audit — runs weekly on Sunday at 04:00
// Audits all agent deployments and updates certification levels + maturity scores
Schedule::command('dwca:audit')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->name('dwca-weekly-audit');
