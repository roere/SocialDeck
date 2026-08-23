FROM php:8.3.33-fpm

RUN docker-php-ext-install pdo_mysql

COPY php-security.ini /usr/local/etc/php/conf.d/99-social-post-security.ini
WORKDIR /var/www/app
