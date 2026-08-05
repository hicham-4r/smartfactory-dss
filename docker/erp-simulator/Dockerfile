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
        libfcgi-bin \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        pcntl \
        pdo_mysql \
        sockets \
        zip \
    && pecl install "redis-${PHPREDIS_VERSION}" \
    && docker-php-ext-enable redis \
    && php -r '$required = ["bcmath", "curl", "dom", "exif", "fileinfo", "gd", "iconv", "intl", "mbstring", "openssl", "pcntl", "pdo_mysql", "redis", "SimpleXML", "sockets", "tokenizer", "xml", "xmlreader", "xmlwriter", "zip", "Zend OPcache"]; $missing = array_values(array_filter($required, static fn (string $extension): bool => ! extension_loaded($extension))); if ($missing !== []) { fwrite(STDERR, "Missing PHP extensions: ".implode(", ", $missing).PHP_EOL); exit(1); }' \
    && rm -rf /var/lib/apt/lists/* /tmp/pear
COPY --from=composer_binary /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-smartfactory.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-smartfactory-opcache.ini
COPY docker/php/zz-smartfactory-fpm.conf /usr/local/etc/php-fpm.d/zz-smartfactory-fpm.conf
COPY docker/php/entrypoint.sh /usr/local/bin/smartfactory-php-entrypoint

RUN chmod 0755 /usr/local/bin/smartfactory-php-entrypoint

FROM php_runtime AS vendor

WORKDIR /build

COPY sage-erp-simulator/composer.json sage-erp-simulator/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

COPY sage-erp-simulator/app ./app
COPY sage-erp-simulator/bootstrap ./bootstrap
COPY sage-erp-simulator/config ./config
COPY sage-erp-simulator/database ./database
COPY sage-erp-simulator/routes ./routes
COPY sage-erp-simulator/artisan ./
RUN composer dump-autoload \
    --no-dev \
    --classmap-authoritative \
    --no-interaction \
    --no-scripts

FROM php_runtime AS application

ENV APP_ROOT=/var/www/simulator

WORKDIR ${APP_ROOT}

COPY --chown=www-data:www-data sage-erp-simulator/ ./
COPY --from=vendor --chown=www-data:www-data /build/vendor ./vendor

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
