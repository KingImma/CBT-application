#!/bin/sh
set -e

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Running tenant migrations..."
php artisan tenants:migrate --force

echo "==> Seeding central DB..."
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=SubscriptionPlanSeeder --force
echo "==> Clearing Spatie permission cache..."
php artisan cache:forget spatie.permission.cache

php artisan tenants:backfill-user-roles 

echo "==> Generating API documentation (Scribe)..."
php artisan scribe:generate

echo "==> Starting Redis Queue Worker in the background..."
php artisan queue:work redis --queue=default,emails --tries=3 --timeout=120 &

echo "==> Starting Web Server..."
php-fpm -D

exec nginx -g "daemon off;"
