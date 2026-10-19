<?php

use App\Models\User;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\Role;
use App\Models\AdminRole;
use App\Models\AdminProfile;
use App\Models\Division;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->fatproTypeId = \App\Models\AccreditationType::firstOrCreate(['name' => 'First Aid Training Providers'])->id;
});

function guardApplicant(string $email): User
{
    return User::forceCreate([
        'email'        => $email,
        'password'     => bcrypt('password'),
        'role_id'      => Role::firstOrCreate(['name' => 'Applicant'])->id,
        'profile_type' => 'Organization',
    ]);
}

function guardAdmin(string $email): User
{
    $admin = User::forceCreate([
        'email'        => $email,
        'password'     => bcrypt('password'),
        'role_id'      => Role::firstOrCreate(['name' => 'Admin'])->id,
        'profile_type' => 'Individual',
    ]);

    AdminProfile::create([
        'user_id'       => $admin->id,
        'division_id'   => Division::firstOrCreate(['name' => 'HCD'])->id,
        'first_name'    => 'Test',
        'last_name'     => 'Admin',
        'position'      => 'LSO III',
        'admin_role_id' => AdminRole::firstOrCreate(['name' => 'Evaluator'])->id,
    ]);

    return $admin;
}

function guardApplication(User $owner, string $trackingNumber, string $status, string $type = 'new'): Application
{
    $application = Application::create([
        'user_id'               => $owner->id,
        'accreditation_type_id' => test()->fatproTypeId,
        'application_type'      => $type,
        'tracking_number'       => $trackingNumber,
    ]);

    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id'      => ApplicationStatus::firstOrCreate(['name' => $status])->id,
    ]);

    return $application;
}

test('an applicant cannot view another user profile', function () {
    $viewer = guardApplicant('viewer@example.com');
    $other  = guardApplicant('other@example.com');

    $this->actingAs($viewer)->get(route('profile.show', $other->id))->assertForbidden();
});

test('staff can still view another admin profile', function () {
    $viewer = guardAdmin('admin_viewer@example.com');
    $other  = guardAdmin('admin_other@example.com');

    $this->actingAs($viewer)->get(route('profile.show', $other->id))->assertOk();
});

test('tracking resubmit ignores a bare application id', function () {
    $application = guardApplication(guardApplicant('owner1@example.com'), 'ARMS2026-AB0001', 'For Update');

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('track.resubmit.all'), ['application_id' => $application->id])
        ->assertSessionHas('error', 'Application not found.');
});

test('tracking payment upload ignores a bare application id', function () {
    $application = guardApplication(guardApplicant('owner2@example.com'), 'ARMS2026-AB0002', 'Awaiting Payment');

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('track.submit_payment'), [
            'application_id'   => $application->id,
            'proof_of_payment' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHas('error', 'Application not found.');

    expect($application->fresh()->payment)->toBeNull();
});

test('tracking payment upload is refused outside the payment stage', function () {
    $application = guardApplication(guardApplicant('owner3@example.com'), 'ARMS2026-AB0003', 'Under Evaluation');

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('track.submit_payment'), [
            'tracking_number'  => $application->tracking_number,
            'proof_of_payment' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHas('error', 'Invalid action. Proof of payment can only be uploaded while your application is awaiting payment.');

    expect($application->fresh()->payment)->toBeNull();
});

test('tracking forms do not act on renewal applications', function () {
    $application = guardApplication(guardApplicant('owner4@example.com'), 'ARMS2026-AB0004', 'Awaiting Payment', 'renewal');

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('track.submit_payment'), ['tracking_number' => $application->tracking_number])
        ->assertSessionHas('error', 'Application not found.');
});

test('tracking payment upload still works by tracking number', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Notification::fake();
    $application = guardApplication(guardApplicant('owner5@example.com'), 'ARMS2026-AB0005', 'Awaiting Payment');

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('track.submit_payment'), [
            'tracking_number'  => strtolower($application->tracking_number),
            'proof_of_payment' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHas('success');

    expect($application->fresh()->payment?->proof_of_payment_status)->toBe('pending');
});
