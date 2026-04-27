<?php

namespace App\Http\Controllers;

use App\Models\AdminProfile;
use App\Models\PendingAdmin;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminInvitationController extends Controller
{
    /**
     * Show the setup password form.
     */
    public function setupPassword($token)
    {
        $pendingAdmin = PendingAdmin::where('token', $token)->first();

        if (!$pendingAdmin) {
            return redirect('/login')->with('error', 'This invitation link is invalid or has already been used.');
        }

        if ($pendingAdmin->isExpired()) {
            $pendingAdmin->delete();
            return redirect('/login')->with('error', 'This invitation link has expired. Please contact your administrator for a new one.');
        }

        return view('admin.setup_password', compact('token', 'pendingAdmin'));
    }

    /**
     * Store the password, create the user, and activate the account.
     */
    public function storePassword(Request $request, $token)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $pendingAdmin = PendingAdmin::where('token', $token)->first();

        if (!$pendingAdmin || $pendingAdmin->isExpired()) {
            return redirect('/login')->with('error', 'This invitation link is invalid or has expired.');
        }

        if (User::where('email', $pendingAdmin->email)->exists()) {
            $pendingAdmin->delete();
            return redirect('/login')->with('error', 'This email address is already registered.');
        }

        DB::transaction(function () use ($pendingAdmin, $request) {
            $role = Role::where('name', 'Admin')->first();

            // Create User
            $user = User::create([
                'email' => $pendingAdmin->email,
                'password' => Hash::make($request->password),
                'role_id' => $role ? $role->id : 2, // 2 is typically Admin if role not found
                'profile_type' => 'Individual',
                'email_verified_at' => now(),
            ]);

            // Create AdminProfile
            AdminProfile::create([
                'user_id' => $user->id,
                'first_name' => $pendingAdmin->first_name,
                'last_name' => $pendingAdmin->last_name,
                'position' => $pendingAdmin->position,
                'division_id' => $pendingAdmin->division_id,
            ]);

            $pendingAdmin->delete();
        });

        return redirect('/login')->with('success', 'Your admin account has been created successfully. You can now log in.');
    }
}
