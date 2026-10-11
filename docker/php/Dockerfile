FROM php:8.4-fpm-trixie

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
        libmagickwand-dev \
        imagemagick \
        libheif-dev \
        libheif-plugin-libde265 \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
    && pecl install imagick-3.8.0 \
    && docker-php-ext-enable imagick \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/attachments.ini /usr/local/etc/php/conf.d/attachments.ini
COPY docker/php/imagemagick-policy.xml /etc/ImageMagick-7/policy.xml

RUN php -r 'foreach (["JPEG", "PNG", "WEBP", "HEIC"] as $format) { if (!Imagick::queryFormats($format)) { fwrite(STDERR, "Missing image codec: $format\n"); exit(1); } }'

WORKDIR /app
