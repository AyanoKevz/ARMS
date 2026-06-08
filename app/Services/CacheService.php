<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CacheService
 *
 * Centralised cache-key registry and invalidation helper for ARMS.
 * All cache keys are prefixed with 'arms:' to avoid collisions.
 * TTLs (seconds):
 *   - Reference data (rarely changes): 3600 (1 hour)
 *   - Application listings:             120  (2 minutes)
 *   - Dashboard stats:                  300  (5 minutes)
 *   - Public tracking lookup:            60  (1 minute)
 */
class CacheService
{
    // ── TTL constants ────────────────────────────────────────────────────────
    public const TTL_REFERENCE  = 3600;  // 1 hour  — statuses, field definitions
    public const TTL_DASHBOARD  = 300;   // 5 min   — stat cards, charts
    public const TTL_LIST       = 120;   // 2 min   — application listings
    public const TTL_TRACKING   = 60;    // 1 min   — public tracking lookup

    // ── Cache key helpers ────────────────────────────────────────────────────

    public static function dashboardKey(int $year): string
    {
        return "arms:dashboard:{$year}";
    }

    public static function pendingKey(): string
    {
        return 'arms:applications:pending';
    }

    public static function underReviewKey(): string
    {
        return 'arms:applications:under_review';
    }

    public static function archivedKey(): string
    {
        return 'arms:applications:archived';
    }

    public static function renewalPendingKey(): string
    {
        return 'arms:applications:renewal_pending';
    }

    public static function renewalUnderReviewKey(): string
    {
        return 'arms:applications:renewal_under_review';
    }

    public static function trackingKey(string $trackingNumber): string
    {
        return 'arms:track:' . $trackingNumber;
    }

    public static function documentFieldsKey(): string
    {
        return 'arms:document_fields';
    }

    public static function applicationStatusesKey(): string
    {
        return 'arms:application_statuses';
    }

    public static function accreditationTypesKey(): string
    {
        return 'arms:accreditation_types';
    }

    // ── remember() wrapper with graceful fallback ────────────────────────────

    /**
     * Attempt to retrieve from Redis cache. On Redis failure, execute
     * the callback directly so the application degrades gracefully.
     *
     * @param  string    $key
     * @param  int       $ttl  Seconds
     * @param  callable  $callback
     * @return mixed
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Throwable $e) {
            Log::warning("ARMS Cache miss (Redis unavailable) for key [{$key}]: " . $e->getMessage());
            return $callback();
        }
    }

    // ── Invalidation helpers ─────────────────────────────────────────────────

    /**
     * Bust all application listing and dashboard caches.
     * Call this whenever an application changes state.
     */
    public static function bustApplicationCaches(?int $dashboardYear = null): void
    {
        try {
            $year = $dashboardYear ?? now()->year;
            $keys = [
                self::dashboardKey($year),
                self::dashboardKey($year - 1), // bust previous year too (edge case)
                self::pendingKey(),
                self::underReviewKey(),
                self::archivedKey(),
                self::renewalPendingKey(),
                self::renewalUnderReviewKey(),
            ];

            foreach ($keys as $key) {
                Cache::forget($key);
            }
        } catch (\Throwable $e) {
            Log::warning('ARMS Cache bust failed (Redis unavailable): ' . $e->getMessage());
        }
    }

    /**
     * Bust the public tracking cache for a specific tracking number.
     * Call this when documents or statuses change for a tracked application.
     */
    public static function bustTrackingCache(string $trackingNumber): void
    {
        try {
            Cache::forget(self::trackingKey($trackingNumber));
        } catch (\Throwable $e) {
            Log::warning("ARMS Cache bust for tracking [{$trackingNumber}] failed: " . $e->getMessage());
        }
    }

    /**
     * Bust all reference data caches (statuses, document fields, accreditation types).
     * Only needed if an admin changes these records (very rare).
     */
    public static function bustReferenceCaches(): void
    {
        try {
            Cache::forget(self::documentFieldsKey());
            Cache::forget(self::applicationStatusesKey());
            Cache::forget(self::accreditationTypesKey());
        } catch (\Throwable $e) {
            Log::warning('ARMS Reference cache bust failed: ' . $e->getMessage());
        }
    }
}
