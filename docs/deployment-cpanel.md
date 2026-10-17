# Deploying Dot.Agents

Production is `https://agents.infodot.co.za`, on the shared cPanel host that also runs the other Dot apps. Everything goes through GitHub Actions.

| Workflow | Runs | What it does |
|---|---|---|
| `test.yml` | every PR and push; called by `deploy.yml` | PHPUnit on PHP 8.4 (SQLite and PostgreSQL jobs), the frontend build. It is the release gate. |
| `security.yml` | every PR and push | Dependency and secret scanning. |
| `drift.yml` | manual, **read-only** | Compares every tracked file of `main` with the host's copy (sha256) and prints counts and paths. Run it before the takeover. |
| `deploy.yml` | manual, `confirm=adopt` once, then `confirm=deploy` | See below. |
| `server-logs.yml` | manual, read-only | Tails the newest Laravel log. |
| `server-inspect.yml` | manual, read-only | Reports the host's PHP, drivers, running processes and cron lines naming this app. |

## Why there is a takeover step

The read-only inspect run on 2026-10-17 (run 39005614928) found the app folder is **not a git checkout**: it was uploaded or unpacked, last changed on 11 June, and runs `laravel/framework` 12.61.1, months older than `main`. The previous `deploy.yml` assumed a VPS layout with release folders and could not work on this host; it never completed a run. **Run the drift report first**: it says how many files differ from `main`. The host has no Node, so the frontend is built on the runner and synced over.

## First time (once)

1. Repository secrets: `DEPLOY_HOST` (`agents.infodot.co.za`), `DEPLOY_USER` (`infodotc`), `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`. `DEPLOY_PATH` is optional: left unset, the app folder is the one `artisan` found under the docroot.
2. Run **Drift Report**. Read it.
3. Run **Deploy to Production** with `confirm=adopt` once. It makes a rollback copy, turns the folder into a checkout of `main` without deleting anything untracked (`.env`, `storage/`, `database/`), installs dependencies, ships the build, forces `APP_DEBUG=false`, lists pending migrations (it does **not** run them unless you also pass `migrate=migrate`), rebuilds the caches and checks the site.
4. Check the pending-migrations list, take a copy of the database file (SQLite lives inside the app folder, so the rollback copy includes it), then run `confirm=deploy` to apply them.

## Every release after that

Run **Deploy to Production** with `confirm=deploy` (`gh workflow run deploy.yml -f confirm=deploy`). It runs the test gate first.

## Safety nets

- A rollback copy of the app folder (without `vendor/`, `node_modules/`, logs and the build) is written to `~/dot-agents-<mode>-<time>.tgz`; the newest three are kept. To restore: stop writes, `tar xzf` it over the app folder, `php artisan up`.
- A failure after maintenance mode starts **leaves the site down** and says so, with the copy's name. A failure before it says the site is untouched.
- `APP_DEBUG` is forced to `false` on every run. `APP_ENV=local` and an `http://` `APP_URL` are reported as warnings, not changed.
- Git hooks on the host never run during a release (an old checkout once carried a Git LFS hook that aborted a release after maintenance mode started).
- Cron: five scheduled commands (`RunDigitalImmuneSystemCheck`, `retention:detect-purge-candidates`, `approvals:expire-overdue` every 30 minutes, the platform scorecard, `dwca:audit`) and 70 queued classes spread over ten named queues. **Before this pipeline nothing on the host ran the scheduler or a queue worker**, so none of those ran. The deploy installs 2 lines through the same locked, verified crontab block as the other Dot apps; the worker drains every named queue (`critical,security,ai,agent-tasks,agents,governance,billing,notifications,social-commerce,default`), because a plain `queue:work` only reads `default`. Every deploy log prints `== crontab: 2 line(s) for # dot-agents installed, M other line(s) preserved`; M must not shrink.
- The first worker run will find whatever has been queued since the app went live. Check the jobs table size before the first `confirm=deploy`, because a large backlog will drain over many minutes and may send mail or call external APIs.
- The smoke check requests `/`, `/login`, `/up`, the built CSS and a harmless `POST /up`, and fails the run if any is wrong or if a debug page is served.

## Not covered yet

No health document for the Dot.Brain guardian and no nightly backup.
