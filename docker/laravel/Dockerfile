# syntax=docker/dockerfile:1.7

ARG PHP_IMAGE=php:8.3.32-fpm-bookworm
ARG COMPOSER_IMAGE=composer:2.10.2

FROM ${COMPOSER_IMAGE} AS composer_binary

FROM ${PHP_IMAGE} AS php_runtime

ARG PHPREDIS_VERSION=6.3.0

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        libcurl4-openssl-dev \
        libfcgi-bin \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        dom \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        simplexml \
        sockets \
        xml \
        xmlreader \
        xmlwriter \
        zip \
    && pecl install "redis-${PHPREDIS_VERSION}" \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer_binary /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-smartfactory.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-smartfactory-opcache.ini
COPY docker/php/zz-smartfactory-fpm.conf /usr/local/etc/php-fpm.d/zz-smartfactory-fpm.conf
COPY docker/php/entrypoint.sh /usr/local/bin/smartfactory-php-entrypoint

RUN chmod 0755 /usr/local/bin/smartfactory-php-entrypoint

ARG NODE_IMAGE=node:24.16-bookworm-slim

FROM ${NODE_IMAGE} AS frontend

WORKDIR /build

COPY laravel-app/package.json laravel-app/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY laravel-app/resources ./resources
COPY laravel-app/vite.config.js ./
RUN mkdir -p public \
    && npm run build

FROM php_runtime AS vendor

WORKDIR /build

COPY laravel-app/composer.json laravel-app/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

COPY laravel-app/app ./app
COPY laravel-app/bootstrap ./bootstrap
COPY laravel-app/config ./config
COPY laravel-app/database ./database
COPY laravel-app/routes ./routes
COPY laravel-app/artisan ./
RUN composer dump-autoload \
    --no-dev \
    --classmap-authoritative \
    --no-interaction \
    --no-scripts

FROM php_runtime AS application

ENV APP_ROOT=/var/www/html

WORKDIR ${APP_ROOT}

COPY --chown=www-data:www-data laravel-app/ ./
COPY --from=vendor --chown=www-data:www-data /build/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /build/public/build ./public/build

RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
        storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD SCRIPT_NAME=/fpm-ping SCRIPT_FILENAME=/fpm-ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null | grep -q pong || exit 1

ENTRYPOINT ["/usr/local/bin/smartfactory-php-entrypoint"]
CMD ["php-fpm", "-F"]
