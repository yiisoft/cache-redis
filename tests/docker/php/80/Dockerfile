FROM --platform=linux/amd64 php:8.0.28-fpm-alpine

RUN apk add unzip zlib-dev libzip-dev autoconf g++ make

RUN docker-php-ext-install zip
RUN pecl install pcov-1.0.11
RUN docker-php-ext-enable pcov

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER 1

WORKDIR /var/www

ENTRYPOINT ["sh", "tests/docker/php/entrypoint.sh"]
CMD ["sleep", "infinity"]
