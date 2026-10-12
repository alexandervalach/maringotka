FROM php:8.5-fpm-alpine

RUN apk add --no-cache libpq icu-libs \
    && apk add --no-cache --virtual .build-deps postgresql-dev icu-dev \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pgsql intl \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/dev.ini /usr/local/etc/php/conf.d/99-development.ini

WORKDIR /var/www/html