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

            // 1) Applicant Check
            // Since role_id=2 typically means applicant, or you can just check accreditation:
            // "Applicants can only log in if they already have an accreditation number"
            // Wait, we need to distinguish admin vs applicant.
            if ($user->role && strtolower($user->role->name) === 'admin') {
                // Admin Login Logic
                $adminProfile = $user->adminProfile;
                
                if ($adminProfile && $adminProfile->division_id) {
                    // For now, hardcode HCD logic, or make it dynamic if we had Division model with names.
                    // Let's assume division_id 1 is HCD. Actually we can check the division name if available.
                    // For the scope of the request, we redirect to HCD dashboard if they are an Admin.
                    // Better to redirect generic or specific:
                    return redirect()->route('admin.hcd.dashboard');
                }
                
                // Fallback for admins without specific division routing yet
                return redirect()->route('admin.hcd.dashboard');
            } else {
                // Applicant Login Logic
                if (!$user->accreditations()->exists()) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return back()->withErrors([
                        'email' => 'Access Denied: You must have an active accreditation number to access this portal.',
                    ])->onlyInput('email');
                }

                // If they have an accreditation, let them in.
                return redirect()->route('applicant.dashboard');
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
