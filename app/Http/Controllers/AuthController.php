<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            return self::redirectAuthenticatedUser($user, $request);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Centralized logic to redirect authenticated users to their respective dashboards.
     */
    public static function redirectAuthenticatedUser($user, $request = null)
    {
        // Check user account role first
        if ($user->role && strtolower($user->role->name) === 'admin') {
            $adminProfile = $user->adminProfile()->with('division')->first();
            
            if ($adminProfile && $adminProfile->division) {
                $divisionName = strtolower($adminProfile->division->name);
                return redirect()->route("admin.{$divisionName}.dashboard");
            }
            
            return redirect()->route('admin.hcd.dashboard');
            
        } elseif ($user->role && strtolower($user->role->name) === 'applicant') {
            // Check if email is verified
            if (!$user->email_verified_at) {
                Auth::logout();
                if ($request) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
                return redirect()->route('login')->withErrors([
                    'email' => 'Please verify your email address before logging in.',
                ]);
            }

            $accreditations = $user->accreditations()->with('accreditationType')->get();
            
            // STRICT REQUIREMENT: Must have an accreditation to access the portal
            if ($accreditations->isEmpty()) {
                Auth::logout();
                if ($request) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                return redirect()->route('login')->withErrors([
                    'email' => 'Access Denied: You must have an active accreditation number to access this portal.',
                ]);
            }

            // If they have an accreditation, redirect based on the first one
            $firstAccreditationType = $accreditations->first()->accreditationType;
            
            if ($firstAccreditationType) {
                $typeSlug = \Illuminate\Support\Str::slug($firstAccreditationType->name);
                
                if (\Illuminate\Support\Facades\Route::has("applicant.{$typeSlug}.dashboard")) {
                    return redirect()->route("applicant.{$typeSlug}.dashboard");
                }
            }

            return redirect()->route('applicant.dashboard');
        }

        Auth::logout();
        if ($request) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->withErrors([
            'email' => 'Access Denied: Unauthorized account role.',
        ]);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
