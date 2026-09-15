<?php

namespace App\Providers;

use App\Support\UploadLimits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        // Upload ceilings, derived from PHP's own limits and shared with every
        // view so the browser-side guards track the server instead of being
        // hardcoded. See App\Support\UploadLimits for why that matters.
        View::share('armsMaxUploadBytes', UploadLimits::maxTotalUploadBytes());
        View::share('armsMaxFileBytes', UploadLimits::maxFileBytes());
        View::share('armsMaxFileCount', UploadLimits::maxFileCount());

        // ── One-way mail ────────────────────────────────────────────────────
        // Every message ARMS sends is an automated notification. Three separate
        // levers decide where a recipient's response can end up, and they are
        // deliberately pointed at different places:
        //
        //   from        — where a human's Reply goes. A no-reply mailbox.
        //   reply_to    — overrides the above. Left empty for strict one-way.
        //   return_path — where DELIVERY FAILURES go. A monitored mailbox, so
        //                 a dead recipient is still noticed.
        //
        // Set here rather than in the individual mailables so a new one cannot
        // be added without them.
        $replyTo = config('mail.reply_to.address');

        if (!empty($replyTo)) {
            Mail::alwaysReplyTo($replyTo, config('mail.reply_to.name'));
        }

        $returnPath = config('mail.return_path');

        if (!empty($returnPath)) {
            Mail::alwaysReturnPath($returnPath);
        }

        // Mark every message as machine-generated. Well-behaved mail servers
        // then suppress out-of-office and vacation auto-replies, which are
        // otherwise the bulk of what an automated sender receives back.
        // RFC 3834 defines Auto-Submitted; the X- header is Microsoft's.
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $headers = $event->message->getHeaders();

            if (!$headers->has('Auto-Submitted')) {
                $headers->addTextHeader('Auto-Submitted', 'auto-generated');
            }

            if (!$headers->has('X-Auto-Response-Suppress')) {
                $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
            }
        });

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

        // ── Auto-Repair Persistent Storage Symlinks ─────────────────────────
        // Automatically repairs public/storage symlink on web access if Hostinger deployment overwrites it
        if (! $this->app->runningInConsole()) {
            $publicStorageLink = public_path('storage');
            $persistentPublic  = dirname(base_path()) . DIRECTORY_SEPARATOR . 'arms_storage' . DIRECTORY_SEPARATOR . 'public';
            $targetPublic      = is_dir($persistentPublic) ? $persistentPublic : storage_path('app/public');

            if (! is_link($publicStorageLink) || @readlink($publicStorageLink) !== $targetPublic) {
                @unlink($publicStorageLink);
                @rmdir($publicStorageLink);
                try {
                    app('files')->link($targetPublic, $publicStorageLink);
                } catch (\Throwable $e) {
                    // Ignore background link errors on permission restricted environments
                }
            }
        }
    }
}
