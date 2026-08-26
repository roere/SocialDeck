FROM php:8.3.33-fpm

RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql curl \
    && rm -rf /var/lib/apt/lists/* \
    && install -d -o www-data -g www-data -m 0700 /var/lib/socialdeck/media

COPY php-security.ini /usr/local/etc/php/conf.d/99-social-post-security.ini
WORKDIR /var/www/app
