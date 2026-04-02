FROM php:8.2-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    nginx \
    postgresql-dev \
    zip \
    unzip \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pcntl \
        zip \
        mbstring \
        opcache

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first — layer caching means composer install
# only re-runs when composer.json/lock changes, not on every code change
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader

# Copy rest of application
COPY . .

# Finish autoloader now that all files are present
RUN composer dump-autoload --optimize --no-dev

# Fix permissions — php-fpm runs as www-data, needs write access
RUN chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf

EXPOSE 10000

# Entrypoint script handles migrations once on startup
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]