# Deployment Runbook

## Prerequisites

- PHP 8.4+, Composer 2.x
- MySQL 8.0+ (production) / SQLite (development)
- Redis 7.x
- Node.js 20 LTS + npm

---

## 1. Initial Server Setup

```bash
# Install PHP extensions
sudo apt install php8.4 php8.4-fpm php8.4-mysql php8.4-redis \
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
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
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

```bash
# Blue-green deployment sequence:
1. git pull origin main
2. composer install --no-dev --optimize-autoloader
3. npm ci && npm run build
4. php artisan migrate --force
5. php artisan config:cache && php artisan route:cache
6. php artisan view:cache && php artisan event:cache
7. php artisan queue:restart     # Gracefully restart workers
8. sudo service php8.4-fpm restart

# Rollback:
git checkout HEAD~1
php artisan migrate:rollback
php artisan config:cache
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
