<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The reverse of EnsureFatproPortal: pages of the OSH Practitioner /
 * Consultant portal (its renewal), closed to First Aid Training Providers.
 */
class EnsurePractitionerPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->usesPractitionerPortal()) {
            return redirect()
                ->route('applicant.dashboard')
                ->with('error', 'That page is for OSH Practitioners and OSH Consultants only.');
        }

        return $next($request);
    }
}
