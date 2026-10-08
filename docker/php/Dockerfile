FROM php:8.4-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        ca-certificates \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
