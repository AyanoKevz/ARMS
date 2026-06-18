<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders Middleware
 *
 * Injects security-hardening HTTP response headers on every request.
 * These headers protect against clickjacking, MIME sniffing, XSS,
 * and enforce HTTPS via HSTS.
 *
 * Registered as a global web middleware in bootstrap/app.php.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only inject headers on HTTP responses (not streamed binary responses like PDFs).
        if (! method_exists($response, 'header')) {
            return $response;
        }

        // Prevent clickjacking — only allow page to be embedded by pages on the same origin.
        $response->header('X-Frame-Options', 'SAMEORIGIN');

        // Prevent browsers from MIME-type sniffing the response content-type.
        $response->header('X-Content-Type-Options', 'nosniff');

        // Legacy XSS protection for older browsers (modern browsers rely on CSP instead).
        $response->header('X-XSS-Protection', '1; mode=block');

        // Control how much referrer information is sent with requests.
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict access to browser features/APIs not needed by ARMS.
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS: Force HTTPS for 1 year, including all subdomains.
        // Only effective when the site is actually served over HTTPS.
        if (app()->environment('production')) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
