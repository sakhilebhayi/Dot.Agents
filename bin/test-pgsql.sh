#!/usr/bin/env bash
# Run the same test suite against a real Postgres database instead of
# sqlite :memory:. sqlite is lenient about things Postgres rejects outright
# (double-quoted string literals in raw SQL, SQLite-only functions like
# strftime(), non-integer values inserted into smallint columns) — several
# real bugs in this app were only ever found this way. Run this periodically
# to catch that whole class of bug automatically.
#
# IMPORTANT: do NOT invoke this via `php artisan test --configuration=...` —
# Laravel's test command already passes its own --configuration flag to the
# underlying phpunit process internally, and a second one conflicts silently:
# phpunit warns "Option --configuration cannot be used more than once" and
# falls back to running the DEFAULT (sqlite) suite while still exiting as if
# everything ran — a false green result that looks identical to a real pass.
# Overriding the DB_* environment variables directly (as below, matching
# bin/test.sh's own convention) is the only combination confirmed to
# genuinely exercise Postgres end to end.
#
# Requires a dedicated `dot_agents_test` database (never the `dot_agents_pilot`
# dev database, since RefreshDatabase drops and recreates tables every run):
#   createdb dot_agents_test
APP_ENV=testing \
DB_CONNECTION=pgsql \
DB_HOST=127.0.0.1 \
DB_PORT=5432 \
DB_DATABASE=dot_agents_test \
DB_USERNAME=infodot \
DB_PASSWORD=postgres \
CACHE_STORE=array \
SESSION_DRIVER=array \
QUEUE_CONNECTION=sync \
php artisan test "$@"
