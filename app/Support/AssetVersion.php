<?php

namespace App\Support;

/**
 * Appends a cache-busting query string to a local asset URL.
 *
 * Without this, `asset('js/landing.js')` is served under the same URL forever,
 * so a browser that cached an old copy keeps running it after a fix ships. That
 * is not theoretical here: the registration form's upload guard was corrected in
 * a7f2440, yet applicants kept seeing the old hardcoded "50 MB" banner because
 * their browser never re-fetched the file.
 *
 * The stamp is the file's mtime, so it changes exactly when the file does.
 */
class AssetVersion
{
    public static function url(string $path): string
    {
        $url = asset($path);
        $full = public_path($path);

        if (! is_file($full)) {
            return $url;
        }

        return $url . '?v=' . filemtime($full);
    }
}
