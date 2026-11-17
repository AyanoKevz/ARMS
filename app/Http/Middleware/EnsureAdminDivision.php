<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route group to admins of one division.
 *
 *     Route::middleware('admin.division:Accreditation')
 *
 * Runs after `admin`, which has already established the user is staff. The
 * per-role checks below this only know role names; this is what keeps an HCD
 * Team Lead out of the Accreditation division's pages and vice versa.
 */
class EnsureAdminDivision
{
    public function handle(Request $request, Closure $next, string $division): Response
    {
        if (! AdminAccess::inDivision($request->user(), $division)) {
            abort(403, "Unauthorized. This area belongs to the {$division} division.");
        }

        return $next($request);
    }
}
