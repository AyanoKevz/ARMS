<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Show the profile edit page based on user role/type.
     */
    public function index()
    {
        $user = Auth::user();

        // Load the relationship based on profile type
        if ($user->role && strtolower($user->role->name) === 'admin') {
            $user->load('adminProfile.division');
            $profile = $user->adminProfile;
            $layout = 'layouts.admin';
        } elseif ($user->profile_type === 'Organization') {
            $user->load('organizationProfile.authorizedRepresentatives');
            $profile = $user->organizationProfile;
            $layout = 'layouts.applicant';
        } else {
            $user->load('individualProfile');
            $profile = $user->individualProfile;
            $layout = 'layouts.applicant';
        }

        $readOnly = false;
        return view('profile.index', compact('user', 'profile', 'layout', 'readOnly'));
    }

    /**
     * Show a read-only profile of a specific user.
     */
    public function show(\App\Models\User $user)
    {
        $loggedInUser = Auth::user();

        // If trying to view own profile, redirect to the editable index page
        if ($loggedInUser->id === $user->id) {
            return redirect()->route('profile.index');
        }

        // Determine layout based on logged-in user so the sidebar/nav remains consistent for them
        $layout = 'layouts.applicant';
        if ($loggedInUser->role && strtolower($loggedInUser->role->name) === 'admin') {
            $layout = 'layouts.admin';
        }

        // Load the target user's profile relationship
        if ($user->role && strtolower($user->role->name) === 'admin') {
            $user->load('adminProfile.division');
            $profile = $user->adminProfile;
        } elseif ($user->profile_type === 'Organization') {
            $user->load('organizationProfile.authorizedRepresentatives');
            $profile = $user->organizationProfile;
        } else {
            $user->load('individualProfile');
            $profile = $user->individualProfile;
        }

        $readOnly = true;
        return view('profile.index', compact('user', 'profile', 'layout', 'readOnly'));
    }

    /**
     * Update the profile and handle photo upload.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $isAdmin = ($user->role && strtolower($user->role->name) === 'admin');

        // Base validation rules
        $rules = [
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ];

        // Specific rules based on type
        if ($isAdmin) {
            $rules['first_name'] = 'required|string|max:100';
            $rules['last_name']  = 'required|string|max:100';
            $rules['position']   = 'nullable|string|max:100';
        } elseif ($user->profile_type === 'Organization') {
            $rules['name']        = 'required|string|max:255';
            $rules['head_name']   = 'required|string|max:255';
            $rules['address']     = 'required|string|max:500';
            $rules['telephone']   = 'required|string|max:50';
            $rules['designation'] = 'nullable|string|max:100';
            $rules['fax']         = 'nullable|string|max:50';
            $rules['email']       = 'required|email|max:255';
            $rules['rep_full_name'] = 'required|string|max:255';
            $rules['rep_position']  = 'required|string|max:100';
            $rules['rep_contact_number'] = 'required|string|max:50';
            $rules['rep_email']     = 'required|email|max:255';
        } else {
            $rules['first_name']  = 'required|string|max:100';
            $rules['last_name']   = 'required|string|max:100';
            $rules['address']     = 'nullable|string|max:500';
        }

        $validated = $request->validate($rules);

        // 1. Handle Photo Upload
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            // Delete old photo if it exists and isn't a default string
            if ($user->user_photo && Storage::disk('public')->exists(str_replace('storage/', '', $user->user_photo))) {
                Storage::disk('public')->delete(str_replace('storage/', '', $user->user_photo));
            }

            // Store new photo in storage/app/public/profiles
            $path = $file->storeAs('profiles', $filename, 'public');
            
            // Save path accessible via the symlink
            $user->update(['user_photo' => 'storage/' . $path]);
            
            // If they have distinct profile photo fields, sync them up just in case:
            if ($user->profile_type === 'Organization' && $user->organizationProfile) {
                $user->organizationProfile->update(['logo_path' => 'storage/' . $path]);
            } elseif ($user->profile_type === 'Individual' && $user->individualProfile) {
                $user->individualProfile->update(['photo_path' => 'storage/' . $path]);
            }
        }

        // 2. Update specific profile fields
        if ($isAdmin && $user->adminProfile) {
            $user->adminProfile->update([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'position'   => $validated['position'] ?? $user->adminProfile->position,
            ]);
        } elseif ($user->profile_type === 'Organization' && $user->organizationProfile) {
            $user->organizationProfile->update([
                'name'        => $validated['name'],
                'head_name'   => $validated['head_name'],
                'address'     => $validated['address'],
                'telephone'   => $validated['telephone'],
                'designation' => $validated['designation'] ?? $user->organizationProfile->designation,
                'fax'         => $validated['fax'] ?? $user->organizationProfile->fax,
                'email'       => $validated['email'],
            ]);

            $rep = $user->organizationProfile->authorizedRepresentatives()->first();
            if ($rep) {
                $rep->update([
                    'full_name' => $validated['rep_full_name'],
                    'position' => $validated['rep_position'],
                    'contact_number' => $validated['rep_contact_number'],
                    'email' => $validated['rep_email']
                ]);
            } else {
                $user->organizationProfile->authorizedRepresentatives()->create([
                    'full_name' => $validated['rep_full_name'],
                    'position' => $validated['rep_position'],
                    'contact_number' => $validated['rep_contact_number'],
                    'email' => $validated['rep_email']
                ]);
            }
        } elseif ($user->profile_type === 'Individual' && $user->individualProfile) {
            $user->individualProfile->update([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'address'    => $validated['address'] ?? $user->individualProfile->address,
            ]);
        }

        return back()->with('success', 'Profile updated successfully.');
    }
}
