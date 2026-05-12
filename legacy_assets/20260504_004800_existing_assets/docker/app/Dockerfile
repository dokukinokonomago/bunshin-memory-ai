FROM php:8.4-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql intl zip \
    && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/app/start-container.sh /usr/local/bin/start-container

RUN chmod +x /usr/local/bin/start-container

CMD ["start-container"]
