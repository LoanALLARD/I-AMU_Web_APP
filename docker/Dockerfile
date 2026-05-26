# Image PHP 8.1 + Apache pour I-AMU
FROM php:8.1-apache

# Installation des dépendances système et extensions PHP
RUN apt-get update && apt-get install -y \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
        curl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Activation du module rewrite Apache (utile pour les routes propres)
RUN a2enmod rewrite headers

# Composer (depuis l'image officielle)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuration PHP pour le streaming (SSE)
RUN { \
        echo 'output_buffering = Off'; \
        echo 'zlib.output_compression = Off'; \
        echo 'implicit_flush = On'; \
        echo 'max_execution_time = 0'; \
        echo 'memory_limit = 256M'; \
        echo 'date.timezone = Europe/Paris'; \
    } > /usr/local/etc/php/conf.d/iamu.ini

# Configuration Apache : DocumentRoot = public/
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

EXPOSE 80
