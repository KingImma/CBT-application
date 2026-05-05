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

# php artisan config:clear

# echo "==> Caching routes..."
# php artisan route:cache

# echo "==> Caching views..."
# php artisan view:cache

# php artisan queue:clear redis
# php artisan queue:clear redis --queue=emails
# php artisan queue:flush
# php artisan queue:restart
php artisan optimize:clear
php artisan config:clear


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

echo "==> Starting Web Server..."
exec php-fpm -D & nginx -g "daemon off;"
