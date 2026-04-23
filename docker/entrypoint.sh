#!/bin/sh
set -e

# echo "==> Waiting 5 seconds for Docker internal DNS to initialize..."
# sleep 5

# echo "==> Manually nuking compiled caches to prevent boot crashes..."
# rm -f bootstrap/cache/config.php
# rm -f bootstrap/cache/events.php
# rm -f bootstrap/cache/packages.php
# rm -f bootstrap/cache/routes.php
# rm -f bootstrap/cache/services.php

# echo "==> Clearing application cache..."
# php artisan cache:clear

# echo "==> Discovering packages..."
# php artisan package:discover --ansi

# echo "==> Caching config..."
# php artisan config:cache

# echo "==> Caching routes..."
# php artisan route:cache

# echo "==> Caching views..."
# php artisan view:cache

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Running tenant migrations..."
php artisan tenants:migrate --force

# echo "==> Backfilling tenant arm subjects..."
# php artisan tenants:backfill-arm-subjects || true

echo "==> Seeding subscription plans..."
php artisan db:seed --class=SubscriptionPlanSeeder --force

echo "==> Starting Redis Queue Worker in the background..."
# We explicitly call the 'redis' connection here to guarantee it uses your Redis instance.
# The '&' is critical so it runs silently in the background.
php artisan queue:work redis --queue=default,emails --tries=3 --timeout=120 &

echo "==> Starting services with health checks..."

# Start PHP-FPM in background
php-fpm -D &
PHP_FPM_PID=$!

# Wait for PHP-FPM (health check)
echo "==> Waiting for PHP-FPM..."
until curl -s http://localhost:9000/ping > /dev/null; do
  echo "PHP-FPM not ready, waiting..."
  sleep 1
done

echo "==> PHP-FPM ready!"

# Start Nginx
exec nginx -g "daemon off;"
