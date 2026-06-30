FROM php:8.3-fpm-alpine

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
    $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pcntl \
    posix \
    zip \
    mbstring \
    opcache \
    bcmath \
    xml

RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# Install dependencies without generating the autoloader yet (app files are not
# present at this layer, so classmap optimisation would be incomplete).
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-scripts

# Copy the rest of the application and set correct ownership in one layer
COPY --chown=www-data:www-data . .

RUN composer dump-autoload --optimize

RUN php artisan package:discover --ansi

# Ensure storage and bootstrap cache directories are writable by the web process
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf

EXPOSE 10000

# Entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]
