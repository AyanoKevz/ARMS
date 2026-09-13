<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow only staff accounts into the admin portal.
 *
 * The per-role checks inside the admin controllers (checkVerifierAccess,
 * checkTeamLeadAccess, …) are DENY-lists keyed on the admin sub-role name:
 *
 *     $role = auth()->user()?->adminProfile?->adminRole?->name ?? '';
 *     if (strtolower($role) === 'verifier') { abort(403); }
 *
 * An applicant has no admin_profile row, so that expression falls through to
 * '' — which matches none of the blocked names and therefore passes every one
 * of those guards. They separate Evaluator from Verifier; nothing separated
 * staff from applicants, and the route groups only required `auth`. Any
 * logged-in applicant could reach the HCD and Accreditation portals and stream
 * other applicants' documents straight off the file-viewer routes.
 *
 * This is the allow-list that was missing. It answers one question only — is
 * this an admin at all — and leaves which admin may do what to the existing
 * per-role helpers.
 */
class EnsureUserIsAdmin
{
    /**
     * Role names on the `users` table that count as staff.
     *
     * Matches AuthenticateAdminSession::ADMIN_ROLES so the two cannot drift
     * into disagreeing about who is an admin.
     */
    private const ADMIN_ROLES = ['admin', 'super admin'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        $isAdminRole = in_array(strtolower($user->role?->name ?? ''), self::ADMIN_ROLES, true);

        // Both conditions matter. The role says the account is staff; the
        // profile carries the division and admin role every downstream guard
        // reads. A staff account with no profile would sail through the
        // deny-lists exactly the way an applicant does.
        if (! $isAdminRole || ! $user->adminProfile) {
            abort(403, 'Unauthorized. This area is restricted to OSHC staff accounts.');
        }

        return $next($request);
    }
}
