<?php

use App\Models\User;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\ApplicationDocument;
use App\Models\DocumentField;
use App\Models\DocumentType;
use App\Models\UserDocument;
use App\Models\Role;
use App\Models\AdminRole;
use App\Models\AdminProfile;
use App\Models\Division;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('evaluator show page self-heals missing PCT entries', function () {
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $evaluatorAdminRole = AdminRole::firstOrCreate(['name' => 'Evaluator']);
    $division = Division::firstOrCreate(['name' => 'HCD']);

    $evaluator = User::forceCreate([
        'email' => 'eval_test@example.com',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
    ]);

    AdminProfile::create([
        'user_id' => $evaluator->id,
        'division_id' => $division->id,
        'first_name' => 'Test',
        'last_name' => 'Evaluator',
        'position' => 'LSO III',
        'admin_role_id' => $evaluatorAdminRole->id,
    ]);

    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'app_test@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => 7,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-01',
    ]);

    $status = ApplicationStatus::firstOrCreate(['name' => 'Under Evaluation']);
    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id' => $status->id,
        'remarks' => 'Application under evaluation',
    ]);

    $application->pctEntries()->delete();

    $response = $this->actingAs($evaluator)
        ->get(route('admin.hcd.applications.show', $application->id));

    $response->assertStatus(200);
    expect($application->pctEntries()->count())->toBeGreaterThan(0);
});

test('finalizeEvaluation blocks transition if there are rejected documents', function () {
    $this->withoutExceptionHandling();
    Mail::fake();

    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $evaluatorAdminRole = AdminRole::firstOrCreate(['name' => 'Evaluator']);
    $division = Division::firstOrCreate(['name' => 'HCD']);

    $evaluator = User::forceCreate([
        'email' => 'eval_test2@example.com',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
    ]);

    AdminProfile::create([
        'user_id' => $evaluator->id,
        'division_id' => $division->id,
        'first_name' => 'Test',
        'last_name' => 'Evaluator',
        'position' => 'LSO III',
        'admin_role_id' => $evaluatorAdminRole->id,
    ]);

    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'app_test2@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => 7,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-02',
    ]);

    $docType = DocumentType::firstOrCreate([
        'code' => 'TEST_TYPE',
        'name' => 'Test Type',
    ]);

    $field = DocumentField::firstOrCreate([
        'code' => 'TEST_01',
    ], [
        'name' => 'Test Document',
        'input_type' => 'file',
        'document_type_id' => $docType->id,
    ]);

    $userDoc = UserDocument::create([
        'user_id' => $applicant->id,
        'document_field_id' => $field->id,
        'file_path' => 'dummy.pdf',
    ]);

    $appDoc = ApplicationDocument::create([
        'application_id' => $application->id,
        'document_field_id' => $field->id,
        'user_document_id' => $userDoc->id,
        'status' => 'rejected',
    ]);

    $status = ApplicationStatus::firstOrCreate(['name' => 'Under Evaluation']);
    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id' => $status->id,
    ]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($evaluator)
        ->post(route('admin.hcd.applications.finalize_evaluation', $application->id), [
            'evaluations' => [
                [
                    'id' => $appDoc->id,
                    'status' => 'rejected',
                    'remarks' => 'rejected remarks',
                ]
            ]
        ]);

    // Check JSON response for rejection action
    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'action' => 'rejection_sent',
    ]);
});

test('resubmitAll blocks uploads if application status is not For Update', function () {
    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'app_test3@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => 7,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-03',
    ]);

    $status = ApplicationStatus::firstOrCreate(['name' => 'Under Evaluation']);
    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id' => $status->id,
    ]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->post(route('track.resubmit.all'), [
            'application_id' => $application->id,
            'files' => [],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Invalid action. You can only resubmit documents if your application status is "For Update".');
});

test('submitReupload blocks uploads if application status is not For Update', function () {
    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'app_test4@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => 7,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-04',
    ]);

    $status = ApplicationStatus::firstOrCreate(['name' => 'Under Evaluation']);
    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id' => $status->id,
    ]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($applicant)
        ->post(route('applicant.renewal.reupload.store'), [
            'application_id' => $application->id,
            'files' => [],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('resubmit_error', 'Invalid action. You can only resubmit documents if your application status is "For Update".');
});

test('verifier can upload and view scanned certificate', function () {
    $this->withoutExceptionHandling();
    Mail::fake();

    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $verifierAdminRole = AdminRole::firstOrCreate(['name' => 'Verifier']);
    $division = Division::firstOrCreate(['name' => 'HCD']);

    $verifier = User::forceCreate([
        'email' => 'verifier_test@example.com',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
    ]);

    AdminProfile::create([
        'user_id' => $verifier->id,
        'division_id' => $division->id,
        'first_name' => 'Test',
        'last_name' => 'Verifier',
        'position' => 'Verifier',
        'admin_role_id' => $verifierAdminRole->id,
    ]);

    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'app_test5@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => 7,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-05',
    ]);

    $status = ApplicationStatus::firstOrCreate(['name' => 'Approved']);
    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id' => $status->id,
    ]);

    $accreditation = \App\Models\Accreditation::create([
        'user_id' => $applicant->id,
        'application_id' => $application->id,
        'accreditation_type_id' => 7,
        'accreditation_number' => 'FATPRO-TEST-05',
        'date_of_accreditation' => now()->format('Y-m-d'),
        'validity_date' => now()->addYears(2)->format('Y-m-d'),
        'status' => 'active',
    ]);

    // Mock PDF file upload
    $file = \Illuminate\Http\UploadedFile::fake()->create('scanned_certificate.pdf', 100, 'application/pdf');

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($verifier)
        ->post(route('admin.hcd.accreditations.upload_scanned', $accreditation->id), [
            'scanned_certificate' => $file,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $accreditation->refresh();
    expect($accreditation->scanned_certificate)->not->toBeNull();
    expect(\Illuminate\Support\Facades\Storage::disk('local')->exists($accreditation->scanned_certificate))->toBeTrue();

    // View scanned certificate
    $viewResponse = $this->actingAs($verifier)
        ->get(route('admin.hcd.accreditations.view_scanned', $accreditation->id));

    $viewResponse->assertStatus(200);
    $viewResponse->assertHeader('Content-Type', 'application/pdf');

    // Clean up
    \Illuminate\Support\Facades\Storage::disk('local')->delete($accreditation->scanned_certificate);
});
