<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

/**
 * Single active session for admin accounts.
 *
 * Every accreditation decision is attributed to a named evaluator
 * (`updated_by => auth()->id()` on the status logs), so two people working
 * concurrently under one admin login turns that audit trail into fiction.
 *
 * On an admin login, AuthController calls Auth::logoutOtherDevices(), which
 * re-hashes the stored password. Laravel's AuthenticateSession compares that
 * hash against the copy held in each session, so every OTHER session for the
 * same account fails the check on its next request and is signed out — the
 * newest device wins. Nobody is ever locked out, which is why this is preferred
 * over refusing the second login outright: a crashed browser would otherwise
 * bar the user until the session lifetime expired.
 *
 * Applicants are deliberately exempt. A FATPro checking their application on a
 * phone and a laptop is legitimate, and bouncing them earns support calls for
 * no accountability gain.
 */
class AuthenticateAdminSession extends AuthenticateSession
{
    /**
     * Role names that this guard applies to.
     */
    private const ADMIN_ROLES = ['admin', 'super admin'];

    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $this->isAdmin($user)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    /**
     * Send a signed-out admin somewhere that can explain why.
     *
     * The parent flushes the session before throwing, so a flash message would
     * not reliably survive. A query string does.
     */
    protected function redirectTo(Request $request)
    {
        return route('login', ['reason' => 'other_device']);
    }

    private function isAdmin($user): bool
    {
        return in_array(strtolower($user->role?->name ?? ''), self::ADMIN_ROLES, true);
    }
}
