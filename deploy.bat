@echo off
REM ══════════════════════════════════════════════════════════════════════════
REM  ARMS — Production Deployment Script
REM  Run this script on the server after pulling the latest code from git.
REM  Usage: deploy.bat
REM ══════════════════════════════════════════════════════════════════════════

echo.
echo [ARMS] Starting production deployment...
echo.

REM ── Step 1: Install/update Composer dependencies (production only) ─────────
echo [1/7] Installing Composer dependencies (no dev)...
composer install --no-dev --optimize-autoloader --no-interaction
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] Composer install failed. Aborting.
    exit /b 1
)

REM ── Step 2: Run any pending database migrations ───────────────────────────
echo [2/7] Running database migrations...
php artisan migrate --force --no-interaction
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] Migrations failed. Aborting.
    exit /b 1
)

REM ── Step 3: Clear all existing caches (so stale values are not served) ─────
echo [3/7] Clearing old caches...
php artisan optimize:clear

REM ── Step 4: Cache the Laravel framework for maximum boot performance ────────
echo [4/7] Caching framework (config / routes / views / events)...
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

REM ── Step 5: Run the combined optimize command ──────────────────────────────
echo [5/7] Running artisan optimize...
php artisan optimize

REM ── Step 6: Clear and restart the queue (if running queue workers) ─────────
echo [6/7] Restarting queue workers...
php artisan queue:restart

REM ── Step 7: Storage link (run once; safe to re-run) ───────────────────────
echo [7/7] Ensuring storage link exists...
php artisan storage:link --force

echo.
echo [ARMS] Deployment complete!
echo.
echo ── Next steps ──────────────────────────────────────────────────────────
echo  * Ensure Supervisor (or your process manager) is running queue workers.
echo  * Verify HTTPS is active and the SSL certificate is valid.
echo  * Check storage\logs\laravel.log for any boot-time errors.
echo ────────────────────────────────────────────────────────────────────────
echo.
