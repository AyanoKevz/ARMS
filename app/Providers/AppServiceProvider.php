<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── HTTPS & Trusted Proxies (Production) ────────────────────────────
        // Forces all generated URLs (asset(), route(), url()) to use https://.
        // Also trusts reverse-proxy headers (X-Forwarded-For, X-Forwarded-Proto)
        // so the real client IP and scheme are resolved correctly.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            Request::setTrustedProxies(
                ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
                Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO
            );
        }

        // ── Eloquent Strictness ──────────────────────────────────────────────
        // Prevent N+1 queries in development — forces eager loading everywhere.
        // In production, lazy loading is allowed to avoid crashing live users.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Prevent silently discarding unfillable attributes
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
