# Important! Do not use this image in production!

ARG PHP_VERSION
FROM --platform=linux/amd64 php:${PHP_VERSION}-cli-alpine

RUN apk add unzip zlib-dev libzip-dev autoconf g++ make

RUN docker-php-ext-install zip opcache
RUN pecl install pcov-1.0.11
RUN docker-php-ext-enable pcov

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER 1

WORKDIR /var/www

ENTRYPOINT ["sh", "tests/docker/php/entrypoint.sh"]
CMD ["sleep", "infinity"]
