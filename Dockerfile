FROM php:8.3-apache

# System libs + PHP extensions Laravel needs
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl tesseract-ocr tesseract-ocr-eng poppler-utils python3 python3-pil python3-numpy libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip gd bcmath exif pcntl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Node (for Vite / frontend assets)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Point Apache at Laravel's /public folder
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Bigger uploads (grade report uploads)
RUN { echo "upload_max_filesize=20M"; echo "post_max_size=25M"; echo "memory_limit=512M"; } \
    > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
