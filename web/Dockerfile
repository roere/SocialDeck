FROM php:8.3.33-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY security.conf /etc/apache2/conf-available/social-post-security.conf
COPY php-security.ini /usr/local/etc/php/conf.d/99-social-post-security.ini
RUN a2enconf social-post-security
WORKDIR /var/www/app
