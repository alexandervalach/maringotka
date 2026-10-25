FROM php:8.5-fpm-alpine

RUN apk upgrade --no-cache \
    && apk add --no-cache \
      libpq icu-libs libjpeg-turbo libpng libwebp \
    && apk add --no-cache --virtual .build-deps \
      postgresql-dev icu-dev \
      libjpeg-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
      pdo_pgsql pgsql intl gd \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/dev.ini /usr/local/etc/php/conf.d/99-development.ini

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/maringotka-entrypoint

RUN chmod 755 /usr/local/bin/maringotka-entrypoint

ENTRYPOINT ["maringotka-entrypoint"]
CMD ["php-fpm"]