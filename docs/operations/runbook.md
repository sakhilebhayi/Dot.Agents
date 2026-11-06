# Operations runbook

Production is `https://agents.infodot.co.za`: one Laravel app on shared cPanel hosting, PHP 8.4, LiteSpeed, a SQLite file in the app folder. There is no Redis, no Horizon, no Reverb and no long-running process. Everything that happens in the background starts from cron.

## What runs

Three commands run every minute. On this host the deploy writes them to `~/.dot-cron.d/agents.cron` for the shared cron runner, and none stay in the crontab. On a host with no runner they go in the shared crontab block instead (see [deploying](../deployment-cpanel.md)):

| Line | Does |
|---|---|
| `schedule:run` every minute | The schedule in `routes/console.php` |
| `queue:work` every minute | Mail, notifications and everything else on the named queues |
| `queue:work agents --queue=agent-steps --stop-when-empty --max-time=50` every minute | Agent steps |

A web request also runs the first steps of a new run after its response has gone (`RunKick`). The lease on each run keeps that apart from the queue worker.

Scheduled every minute: `agents:sweep-runs` (finds stuck steps and resumes runs), `agents:fire-schedules`, `agents:seal-audit`, the scheduler heartbeat. Daily: `agents:forget-expired` at 02:30 and `agents:backup-database` at 03:00.

## Is it healthy

| Check | Means |
|---|---|
| `GET /up` | PHP answers |
| `GET /ready` | Database, migrations, cache, storage and the scheduler. Use this for an uptime monitor |
| `GET /up/scheduler` | The scheduler ran recently |
| `GET /guardian/health` | The Guardian report. Needs `GUARDIAN_TOKEN` |

## Stop agents now

1. One agent: open it and pause it. Pausing beats anything waiting, including an approved action that has not run.
2. Every workflow: Security, Halt all workflows. It needs the typed confirmation and it is recorded in the audit log.
3. Everything: `php artisan down`. Queue workers and the scheduler do not work while the app is down, and a run keeps its place in the journal until `php artisan up`.

Denying a tool or changing an approval rule applies at the next tool call of a running agent, because the gate reads them live. Removing a tool from an agent's setup applies to the next published version.

## When a run looks stuck

- `waiting_approval` or `waiting_agent` is normal. Nothing is held in memory while it waits.
- A step left `executing` when a worker died is marked unknown by the sweeper. An action that may have happened is never repeated by itself.
- Runs held by the concurrency limit are released with a short delay. A queue with delayed jobs is healthy.

## Backups and restore

`agents:backup-database` writes `storage/backups/agents-YYYY-MM-DD.sqlite` with SQLite's online backup and keeps 14 copies. Those copies are on the same host as the live file. **There is no off-host copy yet**, so losing the host loses them. Until there is one, the honest recovery point is the last copy someone took off the host.

Restore:

1. `php artisan down`
2. Stop the cron lines, or wait for a minute with no worker running.
3. Check the copy first: `php artisan agents:verify-restore storage/backups/agents-YYYY-MM-DD.sqlite`. It opens the copy read-only, runs SQLite's integrity check, lists the main tables with their row counts, and checks this server's `APP_KEY` can read the data. It does not touch the live file.
4. Copy it over `database/database.sqlite`. Delete any `-wal` and `-shm` files beside it.
5. `php artisan up`, then `php artisan agents:verify-audit` and `GET /ready`.

A restore loses everything after the copy, including approvals answered since. Runs that were moving at that moment resume from the journal in the copy. Check any run that had an outside effect after the copy was taken.

The restore has been tested locally only. Run it once on a copy on the host and write down how long it took.

## Audit log

Every audit row is sealed into a hash chain within a minute. `php artisan agents:verify-audit` walks the chain and names the first row that does not match. The chain shows that a row was changed or removed. It does not stop someone with write access to the database from doing so, and it does not prove who did. Retention takes the oldest entries once a person approves the purge, so after an approved purge a business's chain may start part way along. Before any purge is approved, a missing first entry is reported.

## Rotating keys

One `APP_KEY` protects stored instructions, memory, tool secrets and webhook secrets.

1. Generate a new key with `php artisan key:generate --show`.
2. On the host set `APP_PREVIOUS_KEYS` to the old key and `APP_KEY` to the new one, then clear the config cache.
3. New writes use the new key and old values still read. Keep the old key in `APP_PREVIOUS_KEYS` until every encrypted value has been written again. There is no bulk re-encrypt command yet, so keep it.
4. If the old key may have leaked, assume every secret stored with it leaked too: rotate provider keys, `FILES_PLATFORM_SECRET`, trigger secrets, API keys and social credentials as well.

API keys last 90 days and are stored hashed. A trigger's secret is shown once; to replace it, delete the trigger and make a new one.

Remove `.env.bak-secrets` from the host, and treat every rollback tarball as sensitive: each one holds `.env` and the database.

## Load

Measured on a laptop with the scripted model, a throwaway SQLite file and `busy_timeout` of 15 seconds: see the readiness report. A model call happens outside any database transaction, so a slow provider does not hold the write lock.

SQLite stays in its default journal mode. WAL would let readers run during a write, but the deploy's rollback copy and any plain file copy would then miss what sits in the `-wal` file. Writes take the lock at the start of a transaction (`transaction_mode` IMMEDIATE) and wait up to 15 seconds.
