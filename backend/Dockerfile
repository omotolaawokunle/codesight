# ── Stage 1: Composer dependencies ──────────────────────────────────────────
FROM composer:2 AS deps

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ── Stage 2: Production image ────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pcntl \
        zip \
        bcmath \
        mbstring \
        opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Configure OPcache for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy vendor from deps stage
COPY --from=deps /app/vendor ./vendor

# Copy application code
COPY . .

# Generate optimised autoloader and cache configuration
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    || true

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
