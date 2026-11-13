FROM php:8.5-fpm-alpine

RUN apk add --no-cache icu-dev libzip-dev postgresql-dev git unzip

RUN docker-php-ext-configure zip \
    && docker-php-ext-install intl zip pdo_pgsql

WORKDIR /var/www/html
