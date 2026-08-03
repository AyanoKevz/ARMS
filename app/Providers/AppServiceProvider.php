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
        // ── HTTPS & Trusted Proxies (Production / Live SSL Environments) ────
        // Forces all generated URLs (asset(), route(), url()) to use https://
        // when accessed over HTTPS or when APP_ENV is production / APP_URL is https.
        if (
            $this->app->environment('production') ||
            str_starts_with(config('app.url'), 'https://') ||
            request()->header('X-Forwarded-Proto') === 'https' ||
            request()->isSecure()
        ) {
            URL::forceScheme('https');
        }

        Request::setTrustedProxies(
            ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // ── Eloquent Strictness ──────────────────────────────────────────────
        // Prevent N+1 queries in development — log lazy loading violations instead of crashing with fatal 500 exceptions.
        if (! $this->app->isProduction()) {
            Model::preventLazyLoading();
            Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
                logger()->warning(sprintf(
                    'Lazy loading violation: Attempted to lazy load [%s] on model [%s].',
                    $relation,
                    get_class($model)
                ));
            });
        }

        // Prevent silently discarding unfillable attributes
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
