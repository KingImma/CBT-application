#!/bin/sh
set -eux

echo "===== ENTRYPOINT STARTED ====="

php -v
php artisan --version

echo "Clearing application caches (config, events, views, cache)..."
php artisan optimize:clear
php artisan horizon:terminate
php artisan event:clear
php artisan permission:cache-reset

echo "Running central migrations..."
php artisan migrate --force --database=pgsql_direct

echo "Running tenant migrations (including new notifications table)..."
php artisan tenants:migrate --force -vvv < /dev/null

echo "Ensuring assessment schedule format across tenants (heals tenants with stale recorded migrations)..."
php artisan tenants:ensure-assessment-schedule-format < /dev/null

echo "Running seeders..."
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=SubscriptionPlanSeeder --force

# php artisan tenants:backfill-grading-scale
# php artisan exams:recompute-grades

# echo "Running stuck grading jobs"
# php artisan exams:recover-stuck-grading

# echo "backfill results for already graded atempts"
# php artisan exams:backfill-results

# php artisan scribe:generate

echo "Clearing stale queue worker memory..."
php artisan queue:restart

echo "Starting Horizon..."
php artisan horizon > /proc/1/fd/1 2>&1 &

echo "Verify Horizon Running..."
horizon_ok=0
for i in 1 2 3 4 5 6 7 8 9 10; do
  if php artisan horizon:status 2>/dev/null | grep -qi "running"; then
    echo "Horizon confirmed running."
    horizon_ok=1
    break
  fi
  sleep 1
done

if [ "$horizon_ok" -eq 0 ]; then
  echo "WARNING: Horizon did not report running in time. Continuing anyway (non-fatal)."
fi

echo "Starting php-fpm..."
php-fpm -D

echo "Starting nginx..."
export PORT=${PORT:-10000}
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
exec nginx -g "daemon off;"