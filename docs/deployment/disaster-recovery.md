# Disaster Recovery Guide

## Recovery Time Objectives

| Scenario | RTO | RPO |
|----------|-----|-----|
| Application crash | 5 minutes | 0 (stateless) |
| Database corruption | 30 minutes | 1 hour (hourly backups) |
| Redis failure | 2 minutes | 0 (cache only) |
| Full server failure | 2 hours | 1 hour |
| Multi-region outage | 4 hours | 1 hour |

---

## 1. Database Backup

```bash
# Automated daily backup (add to crontab)
# Non-interactive auth: put the password in ~/.pgpass (format
# host:port:database:username:password, chmod 600) or export PGPASSWORD --
# unlike mysql's `-p` (interactive prompt), pg_dump/psql never accept a
# password on the command line.
0 2 * * * pg_dump -h 127.0.0.1 -U dotagents dotagents_prod \
  | gzip > /backups/dotagents_$(date +%Y%m%d).sql.gz

# Verify backup integrity
gunzip -c /backups/dotagents_latest.sql.gz | psql -h 127.0.0.1 -U postgres dotagents_verify

# Retention: 30 daily, 12 monthly, 5 yearly
find /backups -name "*.sql.gz" -mtime +30 -delete
```

## 2. Database Restore

```bash
# Step 1: Stop queue workers
supervisorctl stop all

# Step 2: Put app in maintenance mode
php artisan down --secret="recovery-token"

# Step 3: Restore from backup
gunzip -c /backups/dotagents_YYYYMMDD.sql.gz \
  | psql -h 127.0.0.1 -U dotagents dotagents_prod

# Step 4: Verify row counts
# Postgres has no MySQL-style information_schema.tables.table_rows; the
# equivalent approximate per-table row count comes from the stats
# collector via pg_stat_user_tables.
psql -h 127.0.0.1 -U dotagents -d dotagents_prod -c \
  "SELECT relname AS table_name, n_live_tup AS row_estimate \
   FROM pg_stat_user_tables ORDER BY n_live_tup DESC LIMIT 20;"

# Step 5: Run any missed migrations
php artisan migrate --force

# Step 6: Clear all caches
php artisan cache:clear
php artisan config:cache && php artisan route:cache

# Step 7: Restart workers
supervisorctl start all

# Step 8: Bring app back online
php artisan up
```

## 3. Redis Recovery

Redis is used for cache, sessions, and queues. It is NOT the system of record.

```bash
# If Redis fails, applications fall back to database sessions
# and synchronous processing automatically.

# Restart Redis
sudo systemctl restart redis

# Verify
redis-cli ping  # → PONG

# Flush corrupted data (safe — cache is rebuildable)
redis-cli FLUSHDB

# Queue jobs that were in-flight are NOT lost if:
# - Redis AOF persistence is enabled (recommended)
# - Jobs were serialized with --tries > 1
```

## 4. Application Server Recovery

```bash
# Restore from AMI or container image
# Then re-run deployment steps from runbook.md

# Critical: restore .env from secrets manager
# NEVER store .env in git or backups
aws ssm get-parameter --name "/dotagents/prod/.env" \
  --with-decryption > .env
```

## 5. AI Provider Failover

The platform automatically fails over between AI providers.
If all providers fail:

```bash
# Check circuit breaker status
php artisan tinker --execute="
  \$service = app(App\Services\AI\CircuitBreakerService::class);
  foreach (['openai', 'anthropic', 'google', 'ollama'] as \$p) {
    echo \$p . ': ' . \$service->getState('ai_inference_' . \$p) . PHP_EOL;
  }
"

# Manually reset a circuit breaker
php artisan tinker --execute="
  app(App\Services\AI\CircuitBreakerService::class)->reset('ai_inference_openai');
"
```

## 6. Incident Escalation Matrix

| Severity | Response Time | Escalation Path |
|----------|--------------|-----------------|
| SEV1 — Platform down | 15 min | On-call → CTO |
| SEV2 — Major feature broken | 2 hours | On-call → Engineering lead |
| SEV3 — Performance degraded | 4 hours | Engineering team |
| SEV4 — Minor issue | Next business day | Engineering backlog |

## 7. Data Breach Response

```
IMMEDIATE (0-15 min):
1. Isolate affected systems
2. Rotate all API keys and secrets
3. Revoke all active sessions: php artisan sanctum:prune-expired
4. Enable maintenance mode: php artisan down

SHORT-TERM (15-60 min):
5. Identify affected organizations via audit_logs
6. Notify affected users (GDPR: 72-hour reporting deadline)
7. Forensic analysis of security_events table

RECOVERY:
8. Patch vulnerability
9. Restore clean backup
10. Re-enable with enhanced monitoring
11. File regulatory reports if required
```
