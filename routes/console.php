<?php

use App\Console\Commands\BackupSqliteDatabase;
use App\Http\Controllers\SchedulerProbeController;
use App\Jobs\GenerateMegaV2PlatformScorecard;
use App\Jobs\QueueHeartbeat;
use App\Jobs\RunDigitalImmuneSystemCheck;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

// Liveness of the platform itself. /up/scheduler reports this heartbeat's
// age and answers 503 once it is older than
// SchedulerProbeController::SCHEDULER_STALE_SECONDS; the Scheduler Watchdog
// workflow turns that into a failed (emailed) run every 15 minutes, and the
// Guardian's scheduler check reads the same key.
Schedule::call(fn () => Cache::forever(SchedulerProbeController::SCHEDULER_KEY, now()->toIso8601String()))
    ->everyMinute()
    ->name('scheduler-heartbeat');

// Proves the cron-driven queue worker actually drains jobs, not just that
// the scheduler fires: only a worker that runs this can refresh the key.
Schedule::job(new QueueHeartbeat)->everyFiveMinutes()->name('queue-heartbeat');

// Nightly copy of the SQLite file, which holds the only copy of this app's
// data. The SQLite-backed Dot apps share one host, so each one backs up at its
// own minute. No-op on other database drivers.
Schedule::command(BackupSqliteDatabase::class)->dailyAt('03:00')->onOneServer();
