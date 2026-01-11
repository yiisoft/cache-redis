# Important! Do not use this image in production!

ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli-alpine

RUN apk add unzip zlib-dev libzip-dev libsodium-dev icu-dev autoconf g++ make

RUN docker-php-ext-install zip bcmath sodium intl \
    && docker-php-ext-install opcache || true # For backward compatibility with PHP versions below 8.5
RUN pecl install pcov
RUN docker-php-ext-enable pcov

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER 1

WORKDIR /var/www

ENTRYPOINT ["sh", "tests/docker/php/entrypoint.sh"]
CMD ["sleep", "infinity"]
