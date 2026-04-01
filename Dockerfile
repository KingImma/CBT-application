FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY . /var/www/html
WORKDIR /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

COPY docker/nginx.conf /etc/nginx/nginx.conf

EXPOSE 10000

CMD php artisan migrate --force && php-fpm -D && nginx -g "daemon off;"