<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep OSH Practitioners (the Accreditation division's portal) out of the
 * FATPro-only applicant pages: instructors, first-aider certificates, the
 * NTC / Post Training reports and FATPro's renewal. Their sidebar does not
 * link to these; this stops a typed or bookmarked URL.
 */
class EnsureFatproPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->usesPractitionerPortal()) {
            return redirect()
                ->route('applicant.dashboard')
                ->with('error', 'That page is for First Aid Training Providers only.');
        }

        return $next($request);
    }
}
