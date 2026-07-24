<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('accreditation:expiry-check')->daily();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Inject security response headers on every web request.
        $middleware->web(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Append archived-account guard to every authenticated web request.
        // This force-logs out applicants whose accreditations have all been archived.
        $middleware->web(append: [
            \App\Http\Middleware\CheckArchivedAccount::class,
        ]);

        $middleware->alias([
            'prevent-back-history' => \App\Http\Middleware\PreventBackHistory::class,
            'check-archived'       => \App\Http\Middleware\CheckArchivedAccount::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

