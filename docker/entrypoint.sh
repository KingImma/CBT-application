#!/bin/sh
set -e

echo "==> Clearing old caches..."
php artisan config:clear
php artisan cache:clear

echo "==> Caching config..."
php artisan config:cache

echo "==> Caching routes..."
php artisan route:cache

echo "==> Caching views..."
php artisan view:cache

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Seeding subscription plans..."
php artisan db:seed --class=SubscriptionPlanSeeder --force

echo "==> Publishing Horizon assets..."
php artisan horizon:publish

echo "==> Starting PHP-FPM..."
php-fpm -D

echo "==> Starting Nginx..."
exec nginx -g "daemon off;"