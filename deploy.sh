#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════
#  ARMS — Production deployment (Hostinger / Linux)
#
#  Run from the project folder after pulling the latest code:
#      bash deploy.sh
#
#  deploy.bat is the Windows equivalent for a local Laragon machine.
#
#  Does NOT run migrate:fresh or db:seed — those wipe or overwrite data and
#  are run by hand, deliberately, when a rebuild is intended.
#
#  Hostinger hPanel → Advanced → Cron Jobs needs these two, every minute
#  (replace the path with this project's folder):
#
#    cd /home/USER/domains/DOMAIN/ARMS && php artisan schedule:run >> /dev/null 2>&1
#    cd /home/USER/domains/DOMAIN/ARMS && php artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> /dev/null 2>&1
#
#  --stop-when-empty and --max-time matter: without them each minute's cron
#  starts another worker that never exits, and shared hosting soon kills the
#  account for too many processes.
# ══════════════════════════════════════════════════════════════════════════
set -euo pipefail

cd "$(dirname "$0")"

echo "[ARMS] Starting production deployment..."

echo "[1/6] Installing Composer dependencies (no dev)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "[2/6] Running pending migrations..."
php artisan migrate --force --no-interaction

echo "[3/6] Clearing old caches..."
php artisan optimize:clear

echo "[4/6] Caching config, routes, views and events..."
php artisan optimize

echo "[5/6] Telling queue workers to pick up the new code..."
php artisan queue:restart

echo "[6/6] Ensuring the storage link exists..."
php artisan storage:link --force || true

echo "[ARMS] Deployment complete."
echo "  * Check storage/logs for boot errors."
echo "  * Failed emails, if any: php artisan queue:failed"
