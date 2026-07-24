<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckArchivedAccount
{
    /**
     * Handle an incoming request.
     *
     * If the authenticated user is an applicant whose accreditations are ALL archived,
     * immediately invalidate their session and redirect them to the login page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Only applies to authenticated applicant users
        if ($user && $user->role && strtolower($user->role->name) === 'applicant') {
            $accreditations = $user->accreditations()->get(['status']);

            // If they have accreditations and every single one is archived — force logout
            if ($accreditations->isNotEmpty() && $accreditations->every(fn($acc) => $acc->status === 'archived')) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Your account has been archived. Please contact OSHC for assistance.',
                ]);
            }
        }

        return $next($request);
    }
}
