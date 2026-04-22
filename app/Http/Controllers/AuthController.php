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

            // Check user account role first
            if ($user->role && strtolower($user->role->name) === 'admin') {
                // Admin Login Logic
                $adminProfile = $user->adminProfile()->with('division')->first();
                
                if ($adminProfile && $adminProfile->division) {
                    $divisionName = strtolower($adminProfile->division->name);
                    return redirect()->route("admin.{$divisionName}.dashboard");
                }
                
                // Fallback for admins without specific division routing yet
                return redirect()->route('admin.hcd.dashboard');
                
            } elseif ($user->role && strtolower($user->role->name) === 'applicant') {
                // Applicant Login Logic
                $accreditations = $user->accreditations()->with('accreditationType')->get();
                
                if ($accreditations->isEmpty()) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return back()->withErrors([
                        'email' => 'Access Denied: You must have an active accreditation number to access this portal.',
                    ])->onlyInput('email');
                }

                // If they have an accreditation, redirect based on accreditation type.
                $firstAccreditationType = $accreditations->first()->accreditationType;
                
                if ($firstAccreditationType) {
                    $typeSlug = \Illuminate\Support\Str::slug($firstAccreditationType->name);
                    
                    if (\Illuminate\Support\Facades\Route::has("applicant.{$typeSlug}.dashboard")) {
                        return redirect()->route("applicant.{$typeSlug}.dashboard");
                    }
                }

                // Fallback applicant dashboard
                return redirect()->route('applicant.dashboard');
                
            } else {
                // Unrecognized or missing role
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Access Denied: Unauthorized account role.',
                ])->onlyInput('email');
            }
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
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
