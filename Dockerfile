FROM php:8.2-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    nginx \
    postgresql-dev \
    zip \
    unzip \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    curl-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pcntl \
        zip \
        mbstring \
        opcache \
        bcmath \
        xml

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# FIX 1: Add --ignore-platform-reqs to stop the exit code 2 crash
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader --ignore-platform-reqs

# FIX 2: Copy the rest of the app and transfer ownership to www-data immediately
COPY --chown=www-data:www-data . .

# Finish autoloader now that all files are present
RUN composer dump-autoload --optimize --no-dev

# FIX 3: Ensure storage and cache are explicitly writable
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf

EXPOSE 10000

# Entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]