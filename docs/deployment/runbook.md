# Deployment Runbook

## Prerequisites

- PHP 8.4+, Composer 2.x
- PostgreSQL 16+ (production) / SQLite (development)
- Redis 7.x
- Node.js 20 LTS + npm

---

## 1. Initial Server Setup

```bash
# Install PHP extensions
sudo apt install php8.4 php8.4-fpm php8.4-pgsql php8.4-redis \
  php8.4-xml php8.4-curl php8.4-zip php8.4-mbstring

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node
nvm install 20 && nvm use 20
```

## 2. Application Deployment

```bash
# Clone
git clone https://github.com/sakhileb/Dot.Agents.git /var/www/dotagents
cd /var/www/dotagents

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Configure environment
cp .env.example .env
php artisan key:generate

# Edit .env — required values:
# APP_URL, DB_*, REDIS_*, OPENAI_API_KEY, STRIPE_*
nano .env

# Migrate database
php artisan migrate --force

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## 3. Required Environment Variables

```dotenv
# Application
APP_NAME="Dot.Agents"
APP_ENV=production
APP_KEY=                      # Generated via php artisan key:generate
APP_URL=https://your-domain.com
APP_DEBUG=false

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dotagents
DB_USERNAME=dotagents
DB_PASSWORD=

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=

# Queue & Cache
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

# AI Providers
OPENAI_API_KEY=               # Required — primary AI provider
ANTHROPIC_API_KEY=            # Optional — failover provider 1
GOOGLE_AI_API_KEY=            # Optional — failover provider 2
OLLAMA_HOST=                  # Optional — self-hosted failover

# Stripe Billing
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Mail
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@your-domain.com
```

## 4. Queue Workers

```bash
# Start queue workers (production — use supervisor)
php artisan queue:work redis --queue=governance,notifications,default \
  --tries=3 --backoff=10 --max-time=3600

# Supervisor config: /etc/supervisor/conf.d/dotagents-worker.conf
[program:dotagents-worker]
command=php /var/www/dotagents/artisan queue:work redis \
  --queue=governance,notifications,default \
  --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/dotagents-worker.log
```

## 5. Scheduler

```bash
# Add to crontab (crontab -e):
* * * * * cd /var/www/dotagents && php artisan schedule:run >> /dev/null 2>&1
```

## 6. Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/dotagents/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

## 7. Zero-Downtime Deployment (CI/CD)

**This section describes what `.github/workflows/deploy.yml` actually does.**
It is not the `git pull`-in-place flow this section used to describe — that
predates the release/shared/current layout below and doesn't match the real
pipeline. Corrected 2026-09-19 as part of the deploy.yml transfer-step audit
(see `docs/feature-completion-ledger.md`, "Phase 6 — second pass on
deploy.yml").

### 7.1 One-time prerequisites (before the *first* CI-driven deploy)

`deploy.yml` builds the app on the CI runner, ships the built archive to the
target host, and symlinks it into place — it never creates or populates
anything under `shared/`, and it assumes the directory layout below already
exists. A human must do the following once, by hand, before the first real
deploy can succeed:

```bash
# Create the release/current/shared layout
sudo mkdir -p /var/www/dotagents/{releases,shared}
sudo mkdir -p /var/www/dotagents/shared/storage/{app/public,app/private,framework/cache/data,framework/sessions,framework/testing,framework/views,logs}
sudo chown -R www-data:www-data /var/www/dotagents

# Hand-provision the shared .env with REAL production values (DB, Redis,
# API keys, etc.) -- deploy.yml only ever symlinks $SHARED_DIR/.env into
# each release, it never creates or edits it.
sudo -u www-data cp /path/to/checked-out/repo/.env.example /var/www/dotagents/shared/.env
sudo -u www-data nano /var/www/dotagents/shared/.env
sudo chmod 640 /var/www/dotagents/shared/.env
```

The very first `current` symlink doesn't exist yet either — the first
successful CI-driven deploy creates it (`ln -sfn "$RELEASE_DIR" "$CURRENT_DIR"`
in the deploy script). Point Nginx's `root` (see §6) at
`/var/www/dotagents/current/public` once that first deploy has run.

### 7.2 Passwordless sudo for the CI deploy user

`deploy.yml` runs `sudo systemctl reload php8.4-fpm` non-interactively over
SSH (via `appleboy/ssh-action`, no TTY). If `sudo` ever prompts for a
password there, the step hangs until the action's `command_timeout` (10m)
kills it instead of failing fast. The SSH user behind `secrets.DEPLOY_USER`
needs a `NOPASSWD` sudoers entry scoped to exactly that command:

```bash
# /etc/sudoers.d/dotagents-deploy — replace `deploy_user` with whatever
# account secrets.DEPLOY_USER actually is on this host.
deploy_user ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm
```

Validate with `visudo -cf /etc/sudoers.d/dotagents-deploy` before relying on
it. Without this, every deploy and every rollback that reaches the
`sudo systemctl reload` line will hang rather than fail cleanly.

### 7.3 What the pipeline does per deploy

```bash
# On the CI runner (GitHub Actions):
1. composer install --no-dev --optimize-autoloader
2. npm ci && npm run build
3. tar -czf a release archive (excludes .git, node_modules, tests,
   .github, storage -- storage/ is always the shared one, never shipped)
4. Transfer the archive to the target host (appleboy/scp-action, to /tmp)

# Over SSH on the target host (appleboy/ssh-action):
5. Extract into a new /var/www/dotagents/releases/<timestamp>/ directory
6. Symlink in $SHARED_DIR/.env and $SHARED_DIR/storage
7. php artisan migrate --force --no-interaction
8. php artisan config:cache / route:cache / view:cache / event:cache
9. Atomic cutover: ln -sfn the new release to /var/www/dotagents/current
10. sudo systemctl reload php8.4-fpm      # zero-downtime, not a full restart
11. php artisan queue:restart             # workers exit gracefully; a
                                           # process supervisor (see §4) must
                                           # already be configured to
                                           # auto-restart them
12. Health-check retry against /api/health; exit 1 on failure to trigger
    the rollback below
13. Prune releases beyond the last 5

# Rollback (automatic, only if step 5-12 above actually failed):
- Re-point current to the previous release directory (by mtime)
- sudo systemctl reload php8.4-fpm
- php artisan queue:restart
- Health-check the rollback target; warn (don't fail) if still unhealthy
```

## 8. Health Checks

```bash
# Application health
curl https://your-domain.com/up

# Queue health
php artisan queue:monitor redis:default,redis:governance

# Database
php artisan db:show

# Cache
php artisan cache:show
```

## 9. Post-Deployment Verification

```bash
# Run smoke tests
php artisan test --compact tests/Feature/Api/

# Verify key routes
curl -I https://your-domain.com/api/v1/me \
  -H "Authorization: Bearer {token}"

# Check logs
tail -f storage/logs/laravel.log
```
