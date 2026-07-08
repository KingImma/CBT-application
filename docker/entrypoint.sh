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

# echo "Running stuck grading jobs"
# php artisan exams:recover-stuck-grading

# echo "backfill results for already graded atempts"
# php artisan exams:backfill-results

# php artisan scribe:generate

echo "Starting Horizon..."
php artisan horizon > /proc/1/fd/1 2>&1 &

echo "Verify Horizon Running"
php artisan horizon:status

echo "Starting php-fpm..."
php-fpm -D

echo "Starting nginx..."
exec nginx -g "daemon off;"
