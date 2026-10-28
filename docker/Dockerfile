FROM composer:2.10 AS composer
FROM php:8.5-cli-alpine

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache autoconf g++ make \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del autoconf g++ make

WORKDIR /srv/tidbit
