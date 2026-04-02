#!/bin/sh
set -e

echo "==> Caching config..."
php artisan config:cache

echo "==> Caching routes..."
php artisan route:cache

echo "==> Caching views..."
php artisan view:cache

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Publishing Horizon assets..."
php artisan horizon:publish

echo "==> Starting PHP-FPM..."
php-fpm -D

echo "==> Starting Nginx..."
exec nginx -g "daemon off;"