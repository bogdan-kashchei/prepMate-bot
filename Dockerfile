# ─────────────────────────────────────────────────────────────────────────────
# Stage 1: Composer dependencies
# ─────────────────────────────────────────────────────────────────────────────
FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Install production dependencies only — no dev tools in the final image
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-intl

# ─────────────────────────────────────────────────────────────────────────────
# Stage 2: Production image
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

# Labels
LABEL maintainer="interview-bot"
LABEL php="8.3"

# ── System packages ──────────────────────────────────────────────────────────
RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    libcurl \
    curl \
    sqlite-dev \
    curl-dev \
    icu-dev \
    gettext

# ── PHP extensions ───────────────────────────────────────────────────────────
RUN docker-php-ext-install \
    pdo \
    pdo_sqlite \
    opcache \
    curl \
    intl \
    && docker-php-ext-enable opcache

# ── PHP configuration ────────────────────────────────────────────────────────
COPY docker/php/php.ini         /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/opcache.ini     /usr/local/etc/php/conf.d/99-opcache.ini

# ── Nginx configuration ──────────────────────────────────────────────────────
COPY docker/nginx/nginx.conf    /etc/nginx/nginx.conf
COPY docker/nginx/default.conf.template /etc/nginx/http.d/default.conf.template

# ── Supervisor configuration ─────────────────────────────────────────────────
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ── Entrypoint ────────────────────────────────────────────────────────────────
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# ── Application ──────────────────────────────────────────────────────────────
WORKDIR /var/www/html

# Copy vendor from build stage
COPY --from=vendor /app/vendor ./vendor

# Copy application source (respects .dockerignore)
COPY . .

# Copy migrations to a path outside database/ so they survive the Railway
# volume mount (which replaces the entire database/ directory at runtime).
RUN cp -r database/migrations /migrations

# Create SQLite database file and set correct permissions
RUN mkdir -p database storage/logs storage/framework/{cache,sessions,views,testing,nutgram} \
    && touch database/database.sqlite \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 storage database bootstrap/cache

EXPOSE 80

CMD ["/entrypoint.sh"]
