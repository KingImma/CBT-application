# Stage 1: composer install (uses official composer image with PHP)
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Install vendor dependencies in /app/vendor
ENV COMPOSER_MEMORY_LIMIT=-1
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Stage 2: final runtime image
FROM php:8.2-fpm-alpine

# system deps and php extensions build deps
RUN apk add --no-cache \
    bash \
    ca-certificates \
    zip \
    unzip \
    zlib-dev \
    libzip-dev \
    libxml2-dev \
    oniguruma-dev \
    openssl-dev \
    && apk add --no-cache --virtual .build-deps \
    build-base autoconf gcc musl-dev \
    && docker-php-ext-install pdo pdo_pgsql pcntl zip mbstring opcache bcmath xml \
    && apk del .build-deps

# Copy composer binary (optional — composer already used in build stage)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files and set ownership
COPY --chown=www-data:www-data . .

# Copy vendor from composer stage
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=vendor /app/composer.lock /var/www/html/composer.lock
COPY --from=vendor /app/composer.json /var/www/html/composer.json

# Ensure permissions
RUN chown -R www-data:www-data /var/www/html && \
    mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Nginx config (if you plan to run nginx here; recommended: separate container)
COPY docker/nginx.conf /etc/nginx/nginx.conf

EXPOSE 10000

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]
