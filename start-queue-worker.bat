@echo off
REM ══════════════════════════════════════════════════════════════════════════
REM  ARMS — Queue Worker
REM
REM  Emails (rejections, approvals, payment notices, etc.) are dispatched to
REM  the queue so admin actions return immediately instead of waiting on the
REM  Gmail SMTP handshake. THIS WORKER MUST BE RUNNING or those emails will
REM  sit in the `jobs` table unsent.
REM
REM  Leave this window open. To run it unattended, register it as a Windows
REM  scheduled task set to "Run whether user is logged on or not".
REM ══════════════════════════════════════════════════════════════════════════

cd /d "%~dp0"

echo.
echo  [ARMS] Queue worker starting. Keep this window open.
echo  [ARMS] Press Ctrl+C to stop.
echo.

REM --tries=3        retry a failed send up to 3 times before moving to failed_jobs
REM --backoff=30     wait 30s between retries (SMTP hiccups are usually transient)
REM --max-time=3600  recycle the process hourly so long-lived memory is reclaimed
REM --sleep=3        poll interval when the queue is empty

:loop
php artisan queue:work --tries=3 --backoff=30 --max-time=3600 --sleep=3
echo.
echo  [ARMS] Worker exited. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
