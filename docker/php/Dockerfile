FROM php:8.3-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache sqlite sqlite-dev icu-dev libzip-dev oniguruma-dev libxml2-dev git unzip linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install pdo_sqlite intl mbstring dom zip opcache pcntl \
    && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g ${GID} app && adduser -D -u ${UID} -G app app \
    && sed -i 's/^user = www-data/user = app/; s/^group = www-data/group = app/' /usr/local/etc/php-fpm.d/www.conf

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
USER app
