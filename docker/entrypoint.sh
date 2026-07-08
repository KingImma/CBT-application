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

echo "Running stuck grading jobs"
php artisan exams:recover-stuck-grading

echo "Backfill results for already graded attempts"
php artisan exams:backfill-results

echo "Starting php-fpm..."
php-fpm -D

echo "Starting nginx..."
nginx -g "daemon off;" &

# Wait for Nginx to fully start (CRITICAL for port scan)
sleep 3

echo "Starting Horizon in background..."
nohup php artisan horizon > /proc/1/fd/1 2>&1 &

echo "Verify Horizon Running"
sleep 2
php artisan horizon:status || true

echo "===== ENTRYPOINT COMPLETE - CONTAINER STAYING ALIVE ====="

# Keep the container alive (Nginx is already running in background)
# This script exits, but Nginx+Horizon continue running
wait   
