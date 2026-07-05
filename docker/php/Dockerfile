FROM php:8.5-fpm

ARG UID=1000
ARG GID=1000

# Dependências de sistema
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Extensões PHP necessárias para Laravel + MySQL
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions \
        pdo_mysql \
        zip \
        bcmath \
        intl \
        pcntl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Alinha o usuário do container ao host para evitar problemas de permissão no volume
RUN groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} -g ${GID} www-data

WORKDIR /var/www/html

USER www-data
