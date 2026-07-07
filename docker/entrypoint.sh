#!/bin/sh
set -eux

echo "===== ENTRYPOINT STARTED ====="

php -v
php artisan --version

echo "Clearing permission cache..."
php artisan permission:cache-reset

echo "Running migrations..."
php artisan migrate --force

echo "Running tenant migrations..."
php artisan tenants:migrate --force

echo "Running seeders..."
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=SubscriptionPlanSeeder --force

# php artisan scribe:generate

echo "Starting queue..."
php artisan queue:work redis --queue=default,emails --tries=3 --timeout=120 >> /var/www/html/storage/logs/laravel.log 2>&1 &

echo "Starting php-fpm..."
php-fpm -D

echo "Starting nginx..."
exec nginx -g "daemon off;"
