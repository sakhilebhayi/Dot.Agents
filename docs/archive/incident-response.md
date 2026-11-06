# Incident Response Playbook

## Severity Definitions

| Level | Definition | Example |
|-------|-----------|---------|
| **SEV1** | Platform completely unavailable | 500 on all pages |
| **SEV2** | Core feature broken, workaround unavailable | Agents not executing tasks |
| **SEV3** | Feature degraded or workaround available | Slow dashboard load |
| **SEV4** | Minor issue, no user impact | Log noise |

---

## Incident Command Structure

```
Incident Commander (IC)   → Coordinates response, owns communications
Technical Lead (TL)       → Diagnoses and implements fixes
Communications Lead (CL)  → Updates status page, notifies customers
```

---

## Response Runbooks by Incident Type

### INC-001: Platform Unavailable (5xx Errors)

```bash
# 1. Check application logs
tail -200 storage/logs/laravel.log | grep -E "ERROR|CRITICAL"

# 2. Check server resources
top -bn1 | head -20
df -h
free -m

# 3. Check PHP-FPM
sudo systemctl status php8.4-fpm
sudo journalctl -u php8.4-fpm -n 50

# 4. Check database connection
php artisan db:show

# 5. Check Redis
redis-cli ping

# 6. Restart services if needed (in order)
sudo systemctl restart redis
sudo systemctl restart php8.4-fpm
sudo systemctl restart nginx
```

### INC-002: Queue Workers Not Processing

```bash
# Check queue depth
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Check worker processes
ps aux | grep "queue:work"

# Restart workers
php artisan queue:restart
supervisorctl restart all

# Retry failed jobs
php artisan queue:retry all
```

### INC-003: AI Providers All Failing

```bash
# Check circuit breaker states
php artisan tinker --execute="
  \$cb = app(App\Services\AI\CircuitBreakerService::class);
  foreach (['openai','anthropic','google','ollama'] as \$p) {
    echo \$p.': '.\$cb->getState('ai_inference_'.\$p).PHP_EOL;
  }
"

# Check provider API keys are set
php artisan tinker --execute="
  echo 'OpenAI: '.(config('services.openai.api_key') ? 'SET' : 'MISSING').PHP_EOL;
  echo 'Anthropic: '.(config('services.anthropic.api_key') ? 'SET' : 'MISSING').PHP_EOL;
"

# Reset all circuit breakers
php artisan tinker --execute="
  \$cb = app(App\Services\AI\CircuitBreakerService::class);
  foreach (['openai','anthropic','google'] as \$p) {
    \$cb->reset('ai_inference_'.\$p);
    echo 'Reset: '.\$p.PHP_EOL;
  }
"
```

### INC-004: Suspected Security Breach

```bash
# 1. IMMEDIATELY rotate all secrets
# - OPENAI_API_KEY, STRIPE_*, APP_KEY

# 2. Revoke all active sessions
php artisan sanctum:prune-expired --hours=0

# 3. Enable maintenance mode
php artisan down --secret="emergency-$(openssl rand -hex 8)"

# 4. Investigate security events
php artisan tinker --execute="
  App\Models\SecurityEvent::orderByDesc('created_at')->take(20)->get()
    ->each(fn(\$e) => dump(\$e->only(['type','severity','title','created_at'])));
"

# 5. Investigate audit logs for anomalies
# Look for: unusual IP addresses, bulk data access, admin actions

# 6. Identify affected organizations
php artisan tinker --execute="
  App\Models\AuditLog::where('created_at', '>=', now()->subHours(1))
    ->where('event_category', 'security')
    ->distinct('organization_id')
    ->pluck('organization_id');
"
```

### INC-005: Database Connection Pool Exhausted

```bash
# Check active connections
mysql -e "SHOW PROCESSLIST;" | wc -l

# Kill long-running queries (>60s)
mysql -e "
  SELECT id, time, info FROM information_schema.PROCESSLIST
  WHERE command != 'Sleep' AND time > 60;
"

# Check slow query log
tail -100 /var/log/mysql/mysql-slow.log

# Increase connection pool if needed
# Edit config/database.php: options.pool_size
```

---

## Communication Templates

### Internal Slack Alert
```
🚨 [SEV{N}] INCIDENT IN PROGRESS
Time: {timestamp}
Issue: {brief description}
Impact: {what users see}
IC: {name}
Bridge: {link}
Status: Investigating
```

### Customer Status Page Update
```
We are currently investigating reports of {issue description}.
Our team has been alerted and is actively working to resolve this.
We will provide updates every 30 minutes.
Started: {time}
```

### Post-Incident Template (REQUIRED within 24h of SEV1/SEV2)
```
## Incident Report: {title}
Date: {date}
Duration: {X} hours {Y} minutes
Severity: SEV{N}

### Summary
{2-3 sentence summary of what happened and impact}

### Timeline
{timestamp} — First alert / detection
{timestamp} — Incident declared
{timestamp} — Root cause identified
{timestamp} — Fix deployed
{timestamp} — Incident resolved

### Root Cause
{Technical description of root cause}

### Resolution
{What was done to fix it}

### Prevention
{Changes being made to prevent recurrence}
- [ ] Action item 1 (owner, due date)
- [ ] Action item 2 (owner, due date)
```
