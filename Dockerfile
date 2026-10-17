# ─────────────────────────────────────────────────────────────────────────────
# Dot.Agents — Production Dockerfile
# Multi-stage build: dependencies → assets → production image
# ─────────────────────────────────────────────────────────────────────────────

# ── Stage 1: PHP + Composer dependencies ─────────────────────────────────────
FROM php:8.4-fpm-alpine AS php-deps

WORKDIR /app

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    postgresql-dev \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files first (layer cache)
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev)
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# ── Stage 2: Node.js asset build ─────────────────────────────────────────────
FROM node:22-alpine AS node-build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --prefer-offline

COPY . .
COPY --from=php-deps /app/vendor ./vendor

RUN npm run build

# ── Stage 3: Production image ─────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS production

LABEL maintainer="Dot.Agents Platform <engineering@dotagents.com>"
LABEL version="1.0"
LABEL description="Dot.Agents Enterprise AI Workforce Platform"

WORKDIR /var/www/html

# Install runtime dependencies only
# postgresql-libs: pdo_pgsql needs libpq at runtime -- unlike pdo_mysql
# (removed above), which used PHP's bundled mysqlnd driver and needed no
# external client library here.
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng \
    libjpeg-turbo \
    libwebp \
    libzip \
    icu-libs \
    oniguruma \
    postgresql-libs

# Copy PHP extensions from build stage
COPY --from=php-deps /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=php-deps /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# PHP production configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/app.conf

# Nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application from build stages
COPY --from=php-deps /app /var/www/html
COPY --from=node-build /app/public/build /var/www/html/public/build

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create log directory
RUN mkdir -p /var/log/supervisor /var/log/nginx

# Expose HTTP port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
