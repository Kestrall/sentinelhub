FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libssl-dev \
    && docker-php-ext-install pdo_mysql intl zip opcache \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

RUN { echo 'date.timezone=Europe/Paris'; echo 'memory_limit=512M'; } > /usr/local/etc/php/conf.d/sentinelhub.ini
