#!/bin/sh
set -eux

echo "===== ENTRYPOINT STARTED ====="

php -v
php artisan --version

# Pre-flight commands touch cache/queue/migrations. A transient Redis or tenant
# DB failure must never abort this script: a dead entrypoint means nginx never
# starts and the whole app answers 502. Each step is therefore fail-safe.
echo "Clearing application caches (config, events, views, cache)..."
php artisan optimize:clear || echo "WARN: optimize:clear failed (continuing)"
php artisan horizon:terminate || echo "WARN: horizon:terminate failed (continuing)"
php artisan event:clear || echo "WARN: event:clear failed (continuing)"
php artisan permission:cache-reset || echo "WARN: permission:cache-reset failed (continuing)"

echo "Running central migrations..."
php artisan migrate --force --database=pgsql_direct || echo "WARN: central migrations failed (continuing)"

echo "Running tenant migrations (including new notifications table)..."
php artisan tenants:migrate --force -vvv < /dev/null || echo "WARN: tenant migrations failed (continuing)"

echo "Ensuring assessment schedule format across tenants (heals tenants with stale recorded migrations)..."
php artisan tenants:ensure-assessment-schedule-format < /dev/null || echo "WARN: assessment schedule format check failed (continuing)"

echo "Running seeders..."
php artisan db:seed --class=AdminUserSeeder --force || echo "WARN: AdminUserSeeder failed (continuing)"
php artisan db:seed --class=SubscriptionPlanSeeder --force || echo "WARN: SubscriptionPlanSeeder failed (continuing)"

# php artisan tenants:backfill-grading-scale
# php artisan exams:recompute-grades

# echo "Running stuck grading jobs"
# php artisan exams:recover-stuck-grading

# echo "backfill results for already graded atempts"
# php artisan exams:backfill-results

# php artisan scribe:generate

echo "Clearing stale queue worker memory..."
php artisan queue:restart || echo "WARN: queue:restart failed (continuing)"

echo "Starting Horizon..."
php artisan horizon > /proc/1/fd/1 2>&1 &

echo "Starting scheduler..."
php artisan schedule:work > /proc/1/fd/1 2>&1 &

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
