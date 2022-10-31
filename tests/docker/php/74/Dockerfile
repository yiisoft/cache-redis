FROM --platform=linux/amd64 php:7.4.32-fpm-alpine

RUN apk add unzip zlib-dev libzip-dev

RUN docker-php-ext-install zip

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER 1

WORKDIR /var/www

ENTRYPOINT ["sh", "tests/docker/php/entrypoint.sh"]
CMD ["sleep", "infinity"]
