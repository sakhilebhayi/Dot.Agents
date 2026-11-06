# Dot.Agents

AI agents for a business. They do real work with the tools you allow, and hold at a signal whenever something needs a person.

Live at <https://agents.infodot.co.za>. Part of the Dot business platforms; people sign in with InfoDot.

## What it does

- **Agents.** Describe the work, start from a template, or build by hand. Each agent has a job, an owner, limits and versions. Nothing reaches live work until it is published.
- **Runs.** Every run is a journal of steps, pinned to one published version. Runs survive restarts, can be cancelled, and never repeat an action whose outcome is unknown.
- **Tools.** Default deny. The platform checks every call before it runs, whatever the model was told. Outside content reaches an agent only as marked data.
- **Approvals.** The card is written by the system from the actual arguments, and what was approved is what runs.
- **Knowledge and memory.** Documents with sources, keyword search, memory a person can read, correct and remove.
- **Workflows and teams.** Steps in order, side by side or on branches, and a supervisor that hands work to teammates. A workflow is done only when all of it worked.
- **Triggers.** Schedules, signed webhooks, business events and an API.
- **Testing.** Saved test cases, simulated writes, stages before going live.
- **The record.** Timelines, failure explanations, usage and cost counted from what ran.

## Stack

Laravel 13, PHP 8.4, Livewire 3, Prism for model providers, SQLite in production on shared cPanel hosting. There is no Redis and no long-running process: background work starts from cron. See [the runbook](docs/operations/runbook.md).

## Run it locally

```bash
cp .env.example .env
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate
php artisan serve
```

`.env.example` sets `DB_CONNECTION=pgsql`. For SQLite set `DB_CONNECTION=sqlite` and `DB_DATABASE` to a file path. To run agents with no provider key, set `AI_PROVIDER=scripted` outside production: a stand-in answers and every step says so.

## Test

```bash
php -d memory_limit=2G vendor/bin/phpunit
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

Architecture tests hold services to 200 lines, controllers to 100 and Livewire components to 200.

## Documentation

Start at [docs/README.md](docs/README.md). The state of the rebuild, including what has not been tested, is in [the readiness report](docs/programme/R37_READINESS.md).

## License

MIT © [SK Digital / BluPin Incorporated](https://github.com/sakhileb)
