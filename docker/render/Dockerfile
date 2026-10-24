# Production image for Render (free web service). Build context is the repo root:
#   docker build -f docker/render/Dockerfile -t edupredict-render .
# The development image is the root Dockerfile; this one copies the code in,
# installs without dev packages, builds Vite assets, and binds Apache to $PORT.

# --- PHP dependencies -------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY src/composer.json src/composer.lock ./
# The composer image lacks gd/pgsql; the runtime stage has them.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
        --no-interaction --no-progress --ignore-platform-reqs
COPY src/ ./
RUN composer dump-autoload --no-dev --no-scripts --optimize

# --- Frontend assets ---------------------------------------------------------
# Tailwind scans vendor pagination views, so vendor must exist before the build.
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY src/package.json src/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY src/ ./
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# --- Runtime -----------------------------------------------------------------
FROM php:8.3-apache

# Same extensions and OCR tools as the development image (grade OCR shells out
# to python3 + tesseract), plus opcache for production.
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update && apt-get install -y --no-install-recommends \
        tesseract-ocr tesseract-ocr-eng poppler-utils python3 python3-pil python3-numpy \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pdo_mysql mbstring zip gd bcmath exif pcntl opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# Render free instances have 512 MB of RAM: keep few PHP workers.
RUN printf '%s\n' \
        '<IfModule mpm_prefork_module>' \
        '    StartServers 2' \
        '    MinSpareServers 1' \
        '    MaxSpareServers 3' \
        '    MaxRequestWorkers 6' \
        '    MaxConnectionsPerChild 500' \
        '</IfModule>' > /etc/apache2/conf-available/edupredict-mpm.conf \
    && a2enconf edupredict-mpm

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
    && { echo "upload_max_filesize=20M"; echo "post_max_size=25M"; echo "memory_limit=256M"; \
         echo "expose_php=Off"; \
         echo "opcache.enable=1"; echo "opcache.memory_consumption=96"; \
         echo "opcache.max_accelerated_files=20000"; echo "opcache.validate_timestamps=0"; } \
       > /usr/local/etc/php/conf.d/edupredict.ini

WORKDIR /var/www/html
COPY src/ ./
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN php artisan package:discover --ansi \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/render/entrypoint.sh /usr/local/bin/edupredict-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/edupredict-entrypoint \
    && chmod +x /usr/local/bin/edupredict-entrypoint

ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["edupredict-entrypoint"]
CMD ["apache2-foreground"]
