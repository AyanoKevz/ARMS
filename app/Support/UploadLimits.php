<?php

namespace App\Support;

/**
 * Reads PHP's own upload limits so the browser-side guards can be derived from
 * them instead of hardcoding a number in JavaScript.
 *
 * A hardcoded client limit is dangerous when it sits ABOVE post_max_size: those
 * submissions pass validation in the browser, then PHP discards the entire
 * request body and Laravel receives an empty form, so the applicant waits
 * through a long upload and gets a blank error. Deriving the value means the
 * guard stays correct whenever the server config changes, with no code edit.
 */
class UploadLimits
{
    /**
     * Fraction of post_max_size offered to files. The rest covers multipart
     * overhead — boundaries, field names, text inputs — which also counts
     * toward post_max_size but is not part of the file bytes JS measures.
     */
    private const FILE_BUDGET = 0.85;

    /** Total file bytes the browser should allow in one submission. */
    public static function maxTotalUploadBytes(): int
    {
        $postMax = self::toBytes((string) ini_get('post_max_size'));

        // post_max_size=0 means unlimited; fall back to a sane ceiling so the
        // guard still protects against absurd submissions.
        if ($postMax <= 0) {
            return 256 * 1024 * 1024;
        }

        return (int) floor($postMax * self::FILE_BUDGET);
    }

    /**
     * Largest single file, in bytes: the smaller of PHP's limit and the
     * application's own 10 MB rule, so the two can never disagree.
     */
    public static function maxFileBytes(): int
    {
        $appLimit = 10 * 1024 * 1024; // mirrors the max:10240 validation rule
        $phpLimit = self::toBytes((string) ini_get('upload_max_filesize'));

        if ($phpLimit <= 0) {
            return $appLimit;
        }

        return min($appLimit, $phpLimit);
    }

    /** Maximum number of files PHP will accept in one request. */
    public static function maxFileCount(): int
    {
        $count = (int) ini_get('max_file_uploads');

        return $count > 0 ? $count : 20;
    }

    /**
     * Convert a php.ini shorthand size ("40M", "1G", "512K") to bytes.
     */
    public static function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
