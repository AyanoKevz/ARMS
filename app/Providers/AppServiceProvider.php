<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

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
        // Prevent N+1 queries in development — forces eager loading everywhere.
        // In production, lazy loading is allowed to avoid crashing live users.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Prevent silently discarding unfillable attributes
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
