<?php

use App\Models\Accreditation;
use App\Models\AccreditationType;
use App\Models\AdminProfile;
use App\Models\AdminRole;
use App\Models\Application;
use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * An admin who can reach the HCD portal.
 */
function makeSessionAdmin(string $email): User
{
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $division  = Division::firstOrCreate(['name' => 'HCD']);
    $evaluator = AdminRole::firstOrCreate(['name' => 'Evaluator']);

    $user = User::forceCreate([
        'email'        => $email,
        'password'     => bcrypt('secret-password'),
        'role_id'      => $adminRole->id,
        'profile_type' => 'Individual',
    ]);

    AdminProfile::create([
        'user_id'       => $user->id,
        'division_id'   => $division->id,
        'first_name'    => 'Test',
        'last_name'     => 'Admin',
        'position'      => 'LSO III',
        'admin_role_id' => $evaluator->id,
    ]);

    return $user;
}

/**
 * An applicant who passes the portal's "must hold an accreditation" gate.
 */
function makeSessionApplicant(string $email): User
{
    $role = Role::firstOrCreate(['name' => 'Applicant']);
    $type = AccreditationType::firstOrCreate(['name' => 'First Aid Training Providers']);

    $user = User::forceCreate([
        'email'             => $email,
        'password'          => bcrypt('secret-password'),
        'role_id'           => $role->id,
        'profile_type'      => 'Organization',
        'email_verified_at' => now(),
    ]);

    $application = Application::create([
        'user_id'               => $user->id,
        'accreditation_type_id' => $type->id,
        'application_type'      => 'new',
        'tracking_number'       => 'ARMS-TEST-SESSION-01',
    ]);

    Accreditation::create([
        'user_id'               => $user->id,
        'application_id'        => $application->id,
        'accreditation_type_id' => $type->id,
        'accreditation_number'  => '235-260101-900',
        'date_of_accreditation' => now()->subYear()->toDateString(),
        'validity_date'         => now()->addYear()->toDateString(),
        'status'                => 'active',
    ]);

    return $user;
}

test('an admin signing in invalidates sessions held on other devices', function () {
    $admin = makeSessionAdmin('single_session_admin@example.com');
    $originalHash = $admin->password;

    $this->post(route('login.post'), [
        'email'    => $admin->email,
        'password' => 'secret-password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($admin);

    // logoutOtherDevices() re-hashes the stored password; AuthenticateAdminSession
    // compares that hash against the copy each session carries, so every other
    // session fails on its next request.
    expect($admin->fresh()->password)->not->toBe($originalHash);
});

test('a session whose password hash is stale is signed out and told why', function () {
    $admin = makeSessionAdmin('stale_session_admin@example.com');

    $this->post(route('login.post'), [
        'email'    => $admin->email,
        'password' => 'secret-password',
    ])->assertRedirect();

    $this->get(route('profile.index'))->assertOk();

    // Stand in for "signed in elsewhere": the stored hash moves on while this
    // session keeps the old one. forgetUser() drops the guard's in-memory copy so
    // the next request re-reads the row — without it the guard would keep serving
    // the pre-rotation object and the hashes would still agree.
    $admin->forceFill(['password' => bcrypt('rotated-password')])->save();
    $this->app['auth']->guard()->forgetUser();

    $this->get(route('profile.index'))
        ->assertRedirect(route('login', ['reason' => 'other_device']));

    $this->assertGuest();
});

test('applicants are exempt and keep concurrent sessions', function () {
    $applicant = makeSessionApplicant('concurrent_applicant@example.com');
    $originalHash = $applicant->password;

    $this->post(route('login.post'), [
        'email'    => $applicant->email,
        'password' => 'secret-password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($applicant);

    // No re-hash means nothing invalidated the applicant's other sessions.
    expect($applicant->fresh()->password)->toBe($originalHash);
});

test('the login page explains an other-device sign-out', function () {
    $this->get(route('login', ['reason' => 'other_device']))
        ->assertOk()
        ->assertSee('used to sign in on', false)
        ->assertSee('only one active session', false);
});
