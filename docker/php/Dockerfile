FROM php:8.2-fpm AS base

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        ca-certificates \
        git \
        curl; \
    docker-php-ext-install pdo_mysql opcache; \
    rm -rf /var/lib/apt/lists/*

# PHP configs
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Composer (optional in dev; handy in CI/build)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Optimize install layer caching
COPY composer.json composer.lock* ./
RUN set -eux; \
    composer install --no-interaction --prefer-dist --no-progress || true

# App code
COPY . .

# Ensure storage dirs (logs/metrics) exist
RUN mkdir -p storage && chown -R www-data:www-data storage

USER www-data

CMD ["php-fpm"]

