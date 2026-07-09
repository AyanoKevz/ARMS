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
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->fatproTypeId = \App\Models\AccreditationType::firstOrCreate(['name' => 'First Aid Training Providers'])->id;
});

test('evaluator show page self-heals missing PCT entries', function () {
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $evaluatorAdminRole = AdminRole::firstOrCreate(['name' => 'Evaluator']);
    $division = Division::firstOrCreate(['name' => 'HCD']);

    $evaluator = User::forceCreate([
        'email' => 'eval_test@example.com',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'profile_type' => 'Individual',
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
        'accreditation_type_id' => $this->fatproTypeId,
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
        'profile_type' => 'Individual',
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
        'accreditation_type_id' => $this->fatproTypeId,
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
        'accreditation_type_id' => $this->fatproTypeId,
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
        'accreditation_type_id' => $this->fatproTypeId,
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
        'profile_type' => 'Individual',
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
        'accreditation_type_id' => $this->fatproTypeId,
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
        'accreditation_type_id' => $this->fatproTypeId,
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

test('approve/reject buttons are hidden and remarks are readonly when status is rejected or returned', function () {
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $evaluatorAdminRole = AdminRole::firstOrCreate(['name' => 'Evaluator']);
    $division = Division::firstOrCreate(['name' => 'HCD']);

    $evaluator = User::forceCreate([
        'email' => 'eval_test3@example.com',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'profile_type' => 'Individual',
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
        'email' => 'app_test6@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-06',
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
        'remarks' => 'Some remarks here',
    ]);

    // Move application status to For Update
    $status = ApplicationStatus::firstOrCreate(['name' => 'For Update']);
    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id' => $status->id,
    ]);

    $response = $this->actingAs($evaluator)
        ->get(route('admin.hcd.applications.show', $application->id));

    $response->assertStatus(200);

    $html = $response->getContent();
    expect($html)->not->toContain('onclick="setDocStatus(' . $appDoc->id . ', \'approved\')"');
    expect($html)->not->toContain('onclick="setDocStatus(' . $appDoc->id . ', \'rejected\')"');
    expect($html)->toContain('id="remarks-' . $appDoc->id . '"');
    expect($html)->toContain('readonly');
});

test('applicant track and renewal pages display original statuses like "Requires Resubmission" and "Rejected" for rejected or returned items', function () {
    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'app_test_badges@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $newApplication = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-BADGES-NEW',
    ]);

    $renewalApplication = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'renewal',
        'tracking_number' => 'ARMS-TEST-BADGES-REN',
    ]);

    $docType = DocumentType::firstOrCreate([
        'code' => 'TEST_TYPE_BADGES',
        'name' => 'Test Type Badges',
    ]);

    $field = DocumentField::firstOrCreate([
        'code' => 'TEST_BADGE_01',
    ], [
        'name' => 'Test Badge Document',
        'input_type' => 'file',
        'document_type_id' => $docType->id,
    ]);

    $userDoc = UserDocument::create([
        'user_id' => $applicant->id,
        'document_field_id' => $field->id,
        'file_path' => 'dummy.pdf',
    ]);

    $appDocNew = ApplicationDocument::create([
        'application_id' => $newApplication->id,
        'document_field_id' => $field->id,
        'user_document_id' => $userDoc->id,
        'status' => 'rejected',
        'remarks' => 'Requires re-upload remarks',
    ]);

    $appDocRen = ApplicationDocument::create([
        'application_id' => $renewalApplication->id,
        'document_field_id' => $field->id,
        'user_document_id' => $userDoc->id,
        'status' => 'rejected',
        'remarks' => 'Requires re-upload remarks',
    ]);

    // Set status to For Update
    $status = ApplicationStatus::firstOrCreate(['name' => 'For Update']);
    ApplicationStatusLog::create([
        'application_id' => $newApplication->id,
        'status_id' => $status->id,
    ]);
    ApplicationStatusLog::create([
        'application_id' => $renewalApplication->id,
        'status_id' => $status->id,
    ]);

    // 1. Check public tracking page
    $trackResponse = $this->get(route('track', ['tracking_number' => $newApplication->tracking_number]));
    $trackResponse->assertStatus(200);
    $trackHtml = $trackResponse->getContent();
    expect($trackHtml)->toContain('Requires Resubmission');
    expect($trackHtml)->not->toContain('Awaiting Re-upload');

    // 2. Check applicant renewal page
    $renewalResponse = $this->actingAs($applicant)
        ->get(route('applicant.renewal.index'));
    $renewalResponse->assertStatus(200);
    $renewalHtml = $renewalResponse->getContent();
    expect($renewalHtml)->toContain('Rejected');
    expect($renewalHtml)->not->toContain('Awaiting Re-upload');
});

test('pending interview table displays "Pending" status label', function () {
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $evaluatorAdminRole = AdminRole::firstOrCreate(['name' => 'Evaluator']);
    $division = Division::firstOrCreate(['name' => 'HCD']);

    $evaluator = User::forceCreate([
        'email' => 'evaluator_test_tbl@example.com',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'profile_type' => 'Individual',
    ]);

    AdminProfile::create([
        'user_id' => $evaluator->id,
        'division_id' => $division->id,
        'first_name' => 'Test',
        'last_name' => 'Evaluator',
        'position' => 'Evaluator',
        'admin_role_id' => $evaluatorAdminRole->id,
    ]);

    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'app_test_tbl@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-TBL',
    ]);

    $status = ApplicationStatus::firstOrCreate(['name' => 'Scheduled for Interview']);
    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id' => $status->id,
    ]);

    $response = $this->actingAs($evaluator)
        ->get(route('admin.hcd.interviews.pending'));

    $response->assertStatus(200);
    $html = $response->getContent();
    expect($html)->toContain('Pending');
    expect($html)->not->toContain('Scheduled for Interview');
});

test('finalizeEvaluation preserves already approved items and does not reset them to pending', function () {
    $this->withoutExceptionHandling();
    Mail::fake();

    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $evaluatorAdminRole = AdminRole::firstOrCreate(['name' => 'Evaluator']);
    $division = Division::firstOrCreate(['name' => 'HCD']);

    $evaluator = User::forceCreate([
        'email' => 'eval_test_safeguard@example.com',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'profile_type' => 'Individual',
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
        'email' => 'app_test_safeguard@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-SAFEGUARD',
    ]);

    $docType = DocumentType::firstOrCreate([
        'code' => 'TEST_TYPE',
        'name' => 'Test Type',
    ]);

    $field1 = DocumentField::firstOrCreate([
        'code' => 'TEST_01',
    ], [
        'name' => 'Test Document 1',
        'input_type' => 'file',
        'document_type_id' => $docType->id,
    ]);

    $field2 = DocumentField::firstOrCreate([
        'code' => 'TEST_02',
    ], [
        'name' => 'Test Document 2',
        'input_type' => 'file',
        'document_type_id' => $docType->id,
    ]);

    $userDoc1 = UserDocument::create([
        'user_id' => $applicant->id,
        'document_field_id' => $field1->id,
        'file_path' => 'dummy1.pdf',
    ]);

    $userDoc2 = UserDocument::create([
        'user_id' => $applicant->id,
        'document_field_id' => $field2->id,
        'file_path' => 'dummy2.pdf',
    ]);

    // doc1 is already approved
    $appDoc1 = ApplicationDocument::create([
        'application_id' => $application->id,
        'document_field_id' => $field1->id,
        'user_document_id' => $userDoc1->id,
        'status' => 'approved',
    ]);

    // doc2 is rejected
    $appDoc2 = ApplicationDocument::create([
        'application_id' => $application->id,
        'document_field_id' => $field2->id,
        'user_document_id' => $userDoc2->id,
        'status' => 'rejected',
    ]);

    $status = ApplicationStatus::firstOrCreate(['name' => 'Under Evaluation']);
    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id' => $status->id,
    ]);

    // Call finalize_evaluation. In the payload, appDoc1 is sent as 'pending'
    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($evaluator)
        ->post(route('admin.hcd.applications.finalize_evaluation', $application->id), [
            'evaluations' => [
                [
                    'id' => $appDoc1->id,
                    'status' => 'pending',
                ],
                [
                    'id' => $appDoc2->id,
                    'status' => 'rejected',
                    'remarks' => 'Needs update',
                ]
            ]
        ]);

    $response->assertStatus(200);
    
    // Assert appDoc1 stayed approved
    $appDoc1->refresh();
    expect($appDoc1->status)->toBe('approved');

    // Assert appDoc2 stayed rejected
    $appDoc2->refresh();
    expect($appDoc2->status)->toBe('rejected');
});

test('approved application hides documents and credentials if there is a pending renewal', function () {
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $evaluatorAdminRole = AdminRole::firstOrCreate(['name' => 'Evaluator']);
    $division = Division::firstOrCreate(['name' => 'HCD']);

    $evaluator = User::forceCreate([
        'email' => 'eval_test_pending_ren@example.com',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'profile_type' => 'Individual',
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
        'email' => 'app_test_pending_ren@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    // Active approved application
    $approvedApp = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-APPROVED-01',
    ]);

    $approvedStatus = ApplicationStatus::firstOrCreate(['name' => 'Approved']);
    ApplicationStatusLog::create([
        'application_id' => $approvedApp->id,
        'status_id' => $approvedStatus->id,
    ]);

    // Link it to an active accreditation
    \App\Models\Accreditation::create([
        'user_id' => $applicant->id,
        'application_id' => $approvedApp->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'accreditation_number' => 'FATPRO-APPROVED-01',
        'date_of_accreditation' => now()->format('Y-m-d'),
        'validity_date' => now()->addYears(2)->format('Y-m-d'),
        'status' => 'active',
    ]);

    // Pending renewal application
    $renewalApp = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'renewal',
        'tracking_number' => 'ARMS-TEST-RENEWAL-01',
    ]);

    $underEvalStatus = ApplicationStatus::firstOrCreate(['name' => 'Under Evaluation']);
    ApplicationStatusLog::create([
        'application_id' => $renewalApp->id,
        'status_id' => $underEvalStatus->id,
    ]);

    $response = $this->actingAs($evaluator)
        ->get(route('admin.hcd.applications.show', $approvedApp->id));

    $response->assertStatus(200);
    $html = $response->getContent();

    expect($html)->toContain('Currently Applying for Renewal');
    expect($html)->not->toContain('Submitted Documents</h5>');
    expect($html)->not->toContain('Instructor Credentials</h5>');
});

test('applicant instructor list deduplicates instructors and shows the latest version', function () {
    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'app_test_dedup@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    // Active approved application instructor
    $oldInstructor = \App\Models\Instructor::create([
        'user_id' => $applicant->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'status' => 'approved',
    ]);

    // Cloned renewal application instructor (newer)
    $newInstructor = \App\Models\Instructor::create([
        'user_id' => $applicant->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($applicant)
        ->get(route('applicant.instructors.index'));

    $response->assertStatus(200);

    // Retrieve the view data
    $instructors = $response->viewData('instructors');

    expect($instructors->count())->toBe(1);
    expect($instructors->first()->id)->toBe($newInstructor->id);
    expect($instructors->first()->status)->toBe('pending');
});

test('accreditation number middle part updates to current date on renewal', function () {
    $this->withoutExceptionHandling();
    Mail::fake();

    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $verifierAdminRole = AdminRole::firstOrCreate(['name' => 'Verifier']);
    $division = Division::firstOrCreate(['name' => 'HCD']);

    $verifier = User::forceCreate([
        'email' => 'eval_test_renewal_num@example.com',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'profile_type' => 'Individual',
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
        'email' => 'app_test_renewal_num@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    // Create a previous application
    $prevApplication = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-PREV',
    ]);

    // 1. Create a previous active accreditation for the user linked to prevApplication
    $prevAccreditation = \App\Models\Accreditation::create([
        'user_id' => $applicant->id,
        'application_id' => $prevApplication->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'accreditation_number' => '235-240101-048',
        'date_of_accreditation' => '2024-01-01',
        'validity_date' => '2027-01-01',
        'status' => 'active',
    ]);

    // 2. Create a renewal application
    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'renewal',
        'tracking_number' => 'ARMS-TEST-REN-NUM',
    ]);

    // Set status to Payment Verification
    $status = ApplicationStatus::firstOrCreate(['name' => 'Payment Verification']);
    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id' => $status->id,
    ]);

    // Create payment record
    $payment = \App\Models\ApplicationPayment::create([
        'application_id' => $application->id,
        'proof_of_payment' => 'dummy_payment.pdf',
        'proof_of_payment_status' => 'pending',
    ]);

    // Mock PDF file upload
    $file = \Illuminate\Http\UploadedFile::fake()->create('signed_recommendation.pdf', 100, 'application/pdf');

    // Submit payment evaluation approving the payment
    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($verifier)
        ->post(route('admin.hcd.applications.evaluate_payment', $application->id), [
            'signed_recommendation_letter' => $file,
            'proof_of_payment_status' => 'approved',
            'proof_of_payment_remarks' => 'Looks good',
        ]);

    $response->assertRedirect();

    // Verify the old accreditation is expired and has kept its number
    $prevAccreditation->refresh();
    expect($prevAccreditation->status)->toBe('expired');
    expect($prevAccreditation->accreditation_number)->toBe('235-240101-048');

    // Verify a new accreditation record was created with the updated middle part and correct suffix
    $newAccreditation = \App\Models\Accreditation::where('application_id', $application->id)->first();
    expect($newAccreditation)->not->toBeNull();
    $expectedDatePrefix = now()->format('ymd');
    expect($newAccreditation->accreditation_number)->toBe("235-{$expectedDatePrefix}-048");
    expect($newAccreditation->status)->toBe('active');

    // Clean up mock file
    if ($payment->signed_recommendation_letter && \Illuminate\Support\Facades\Storage::disk('local')->exists($payment->signed_recommendation_letter)) {
        \Illuminate\Support\Facades\Storage::disk('local')->delete($payment->signed_recommendation_letter);
    }
});

test('optional documents not uploaded in renewal are not created and thus hidden from admin show page', function () {
    $this->withoutExceptionHandling();
    Mail::fake();

    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'app_test_opt_docs@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    // Create organization profile
    \App\Models\OrganizationProfile::create([
        'user_id' => $applicant->id,
        'name' => 'Test Organization',
        'address' => 'Test Address',
        'email' => 'app_test_opt_docs@example.com',
        'head_name' => 'Head Name',
        'designation' => 'Director',
    ]);

    // Create previous application
    $prevApplication = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-TEST-OPT-PREV',
    ]);

    // Create previous active accreditation
    \App\Models\Accreditation::create([
        'user_id' => $applicant->id,
        'application_id' => $prevApplication->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'accreditation_number' => '235-240101-048',
        'date_of_accreditation' => '2024-01-01',
        'validity_date' => '2027-01-01',
        'status' => 'active',
    ]);

    // Seed document fields: one optional (LEGAL_07) and one required (LEGAL_01)
    $optField = DocumentField::where('code', 'LEGAL_07')->first();
    $reqField = DocumentField::where('code', 'LEGAL_01')->first();

    // Let's seed UserDocument for ALL document fields for the user
    $documentFields = DocumentField::all();
    foreach ($documentFields as $field) {
        $val = null;
        $filePath = null;
        if ($field->input_type === 'file') {
            $filePath = "dummy_files/test_document_{$field->code}.pdf";
        } elseif ($field->input_type === 'date') {
            $val = '2026-01-01';
        } else {
            $val = 'test value';
        }

        UserDocument::create([
            'user_id' => $applicant->id,
            'document_field_id' => $field->id,
            'file_path' => $filePath,
            'value' => $val,
        ]);
    }



    $file = \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    // Post to renewal store without submitting the optional document
    $response = $this->actingAs($applicant)
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->post(route('applicant.renewal.store'), [
            'application_type' => 'renewal',
            'org_name' => 'Test Organization',
            'org_address' => 'Test Address',
            'head_name' => 'Head Name',
            'org_email' => 'app_test_opt_docs@example.com',
            'rep_full_name' => 'Rep Full Name',
            'rep_position' => 'Rep Position',
            'rep_contact_number' => '09171234567',
            'rep_email' => 'rep@example.com',
            'instructors' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'service_agreement' => $file,
                    'credentials' => [
                        'EMS' => [
                            'number' => 'EMS-123',
                            'issued_date' => '2026-01-01',
                            'validity_date' => '2028-01-01',
                            'pdf' => $file,
                        ],
                        'TM1' => [
                            'number' => 'TM1-123',
                            'issued_date' => '2026-01-01',
                            'validity_date' => '2028-01-01',
                            'pdf' => $file,
                        ],
                        'NTTC' => [
                            'number' => 'NTTC-123',
                            'issued_date' => '2026-01-01',
                            'validity_date' => '2028-01-01',
                            'pdf' => $file,
                        ],
                        'BOSH' => [
                            'number' => 'BOSH-123',
                            'validity_date' => '2028-01-01',
                            'training_dates' => 'Jan 1-5, 2026',
                            'pdf' => $file,
                        ],
                    ],
                ],
            ],
        ]);

    $response->assertRedirect();

    // Verify a new renewal application is created
    $renewalApp = Application::where('user_id', $applicant->id)->where('application_type', 'renewal')->first();
    expect($renewalApp)->not->toBeNull();

    // Verify ApplicationDocument for LEGAL_01 was created (copied because it's required)
    $reqAppDoc = ApplicationDocument::where('application_id', $renewalApp->id)
        ->where('document_field_id', $reqField->id)
        ->first();
    expect($reqAppDoc)->not->toBeNull();

    // Verify ApplicationDocument for LEGAL_07 was NOT created (optional and not uploaded)
    $optAppDoc = ApplicationDocument::where('application_id', $renewalApp->id)
        ->where('document_field_id', $optField->id)
        ->first();
    expect($optAppDoc)->toBeNull();
});

test('ntc document evaluation auto-saves immediately without deleting file or sending mail', function () {
    $this->withoutExceptionHandling();
    Mail::fake();

    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $evaluatorAdminRole = AdminRole::firstOrCreate(['name' => 'Evaluator']);
    $division = Division::firstOrCreate(['name' => 'HCD']);

    $evaluator = User::forceCreate([
        'email' => 'evaluator_ntc@example.com',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'profile_type' => 'Individual',
    ]);

    AdminProfile::create([
        'user_id' => $evaluator->id,
        'division_id' => $division->id,
        'first_name' => 'NTC',
        'last_name' => 'Evaluator',
        'position' => 'LSO III',
        'admin_role_id' => $evaluatorAdminRole->id,
    ]);

    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'applicant_ntc@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-NTC-01',
    ]);

    $accreditation = \App\Models\Accreditation::create([
        'user_id' => $applicant->id,
        'application_id' => $application->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'accreditation_number' => '235-240101-049',
        'date_of_accreditation' => '2024-01-01',
        'validity_date' => '2027-01-01',
        'status' => 'active',
    ]);

    $trainingType = \App\Models\NtcTrainingType::first();
    $trainingMode = \App\Models\NtcTrainingMode::first();

    $ntcReport = \App\Models\NtcReport::create([
        'accreditation_id' => $accreditation->id,
        'ntc_training_type_id' => $trainingType->id,
        'ntc_training_mode_id' => $trainingMode->id,
        'training_start_date' => now()->addDays(15)->format('Y-m-d'),
        'training_end_date' => now()->addDays(18)->format('Y-m-d'),
        'status' => 'submitted',
    ]);

    $docType = \App\Models\NtcDocumentType::first();

    $document = \App\Models\NtcDocument::create([
        'ntc_report_id' => $ntcReport->id,
        'ntc_document_type_id' => $docType->id,
        'file_path' => 'dummy_ntc.pdf',
        'original_filename' => 'test_ntc.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_at' => now(),
        'status' => 'pending',
    ]);

    // Send single document evaluate POST request
    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($evaluator)
        ->post(route('admin.hcd.reports.ntc.documents.evaluate', $document->id), [
            'status' => 'rejected',
            'remarks' => 'Incorrect form filled',
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'status' => 'rejected',
        'remarks' => 'Incorrect form filled',
    ]);

    $document->refresh();
    expect($document->status)->toBe('rejected');
    expect($document->remarks)->toBe('Incorrect form filled');
    // Ensure the file was not deleted during auto-save
    expect($document->file_path)->toBe('dummy_ntc.pdf');

    // Verify email was not sent yet
    Mail::assertNothingSent();
});

test('ntc document evaluation buttons and remarks are visible when rejected but file exists, and hidden/readonly when file is deleted', function () {
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $evaluatorAdminRole = AdminRole::firstOrCreate(['name' => 'Evaluator']);
    $division = Division::firstOrCreate(['name' => 'HCD']);

    $evaluator = User::forceCreate([
        'email' => 'evaluator_ntc_ui@example.com',
        'password' => bcrypt('password'),
        'role_id' => $adminRole->id,
        'profile_type' => 'Individual',
    ]);

    AdminProfile::create([
        'user_id' => $evaluator->id,
        'division_id' => $division->id,
        'first_name' => 'NTC UI',
        'last_name' => 'Evaluator',
        'position' => 'LSO III',
        'admin_role_id' => $evaluatorAdminRole->id,
    ]);

    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'applicant_ntc_ui@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-NTC-UI-01',
    ]);

    $accreditation = \App\Models\Accreditation::create([
        'user_id' => $applicant->id,
        'application_id' => $application->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'accreditation_number' => '235-240101-050',
        'date_of_accreditation' => '2024-01-01',
        'validity_date' => '2027-01-01',
        'status' => 'active',
    ]);

    $trainingType = \App\Models\NtcTrainingType::first();
    $trainingMode = \App\Models\NtcTrainingMode::first();

    $ntcReport = \App\Models\NtcReport::create([
        'accreditation_id' => $accreditation->id,
        'ntc_training_type_id' => $trainingType->id,
        'ntc_training_mode_id' => $trainingMode->id,
        'training_start_date' => now()->addDays(15)->format('Y-m-d'),
        'training_end_date' => now()->addDays(18)->format('Y-m-d'),
        'status' => 'submitted',
    ]);

    $docType = \App\Models\NtcDocumentType::first();

    // Document 1: status is rejected but file exists
    $document1 = \App\Models\NtcDocument::create([
        'ntc_report_id' => $ntcReport->id,
        'ntc_document_type_id' => $docType->id,
        'file_path' => 'dummy_ntc1.pdf',
        'original_filename' => 'test_ntc1.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_at' => now(),
        'status' => 'rejected',
    ]);

    // Document 2: status is rejected and file path is null (rejection finalized)
    $document2 = \App\Models\NtcDocument::create([
        'ntc_report_id' => $ntcReport->id,
        'ntc_document_type_id' => $docType->id,
        'file_path' => null,
        'original_filename' => 'test_ntc2.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_at' => now(),
        'status' => 'rejected',
    ]);

    $response = $this->actingAs($evaluator)
        ->get(route('admin.hcd.reports.ntc.show', $ntcReport->id));

    $response->assertStatus(200);
    $html = $response->getContent();

    // Document 1: should contain evaluate buttons and NOT contain readonly
    expect($html)->toContain('onclick="setNtcDocStatus(' . $document1->id . ', \'approved\')"');
    expect($html)->toContain('id="ntc-remarks-' . $document1->id . '"');
    $doc1RemarksBlock = substr($html, strpos($html, 'id="ntc-remarks-' . $document1->id . '"'), 300);
    expect($doc1RemarksBlock)->not->toContain('readonly');

    // Document 2: should NOT contain evaluate buttons, should contain "Awaiting re-upload", and remarks should be readonly
    expect($html)->not->toContain('onclick="setNtcDocStatus(' . $document2->id . ', \'approved\')"');
    expect($html)->toContain('Awaiting re-upload from FATPro');
    $doc2RemarksBlock = substr($html, strpos($html, 'id="ntc-remarks-' . $document2->id . '"'), 300);
    expect($doc2RemarksBlock)->toContain('readonly');
});

test('applicant portal ntc view hides rejection status and form early if file still exists', function () {
    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'applicant_ntc_portal@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-NTC-PORTAL-01',
    ]);

    $accreditation = \App\Models\Accreditation::create([
        'user_id' => $applicant->id,
        'application_id' => $application->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'accreditation_number' => '235-240101-051',
        'date_of_accreditation' => '2024-01-01',
        'validity_date' => '2027-01-01',
        'status' => 'active',
    ]);

    $trainingType = \App\Models\NtcTrainingType::first();
    $trainingMode = \App\Models\NtcTrainingMode::first();

    $ntcReport = \App\Models\NtcReport::create([
        'accreditation_id' => $accreditation->id,
        'ntc_training_type_id' => $trainingType->id,
        'ntc_training_mode_id' => $trainingMode->id,
        'training_start_date' => now()->addDays(15)->format('Y-m-d'),
        'training_end_date' => now()->addDays(18)->format('Y-m-d'),
        'status' => 'submitted',
    ]);

    $docType = \App\Models\NtcDocumentType::first();

    // Document 1: status is rejected but file still exists (not finalized by admin)
    $document1 = \App\Models\NtcDocument::create([
        'ntc_report_id' => $ntcReport->id,
        'ntc_document_type_id' => $docType->id,
        'file_path' => 'dummy_ntc1.pdf',
        'original_filename' => 'test_ntc1.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_at' => now(),
        'status' => 'rejected',
    ]);

    // Document 2: status is rejected and file path is null (finalized rejection)
    $document2 = \App\Models\NtcDocument::create([
        'ntc_report_id' => $ntcReport->id,
        'ntc_document_type_id' => $docType->id,
        'file_path' => null,
        'original_filename' => 'test_ntc2.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_at' => now(),
        'status' => 'rejected',
    ]);

    $response = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'));

    $response->assertStatus(200);
    $html = $response->getContent();

    // The NTC report status should show "Requires Re-submission" because document 2 has NO file (finalized rejection)
    expect($html)->toContain('Requires Re-submission');

    // Document 1: status is rejected but file path is NOT null. So it should show "Under Review" instead of "Rejected"
    expect($html)->toContain('Under Review');
    expect($html)->not->toContain('name="files[' . $document1->id . ']"');

    // Document 2: status is rejected and file path IS null. So it should show "Rejected" and render the re-upload form
    expect($html)->toContain('Rejected');
    expect($html)->toContain('action="' . route('applicant.ntc.reupload_batch', $ntcReport->id) . '"');
    expect($html)->toContain('name="files[' . $document2->id . ']"');
});

test('applicant portal ntc report does not show Action Required or Requires Re-submission if all rejections are not finalized', function () {
    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);
    $applicant = User::forceCreate([
        'email' => 'applicant_ntc_portal2@example.com',
        'password' => bcrypt('password'),
        'role_id' => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $application = Application::create([
        'user_id' => $applicant->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'application_type' => 'new',
        'tracking_number' => 'ARMS-NTC-PORTAL-02',
    ]);

    $accreditation = \App\Models\Accreditation::create([
        'user_id' => $applicant->id,
        'application_id' => $application->id,
        'accreditation_type_id' => $this->fatproTypeId,
        'accreditation_number' => '235-240101-052',
        'date_of_accreditation' => '2024-01-01',
        'validity_date' => '2027-01-01',
        'status' => 'active',
    ]);

    $trainingType = \App\Models\NtcTrainingType::first();
    $trainingMode = \App\Models\NtcTrainingMode::first();

    $ntcReport = \App\Models\NtcReport::create([
        'accreditation_id' => $accreditation->id,
        'ntc_training_type_id' => $trainingType->id,
        'ntc_training_mode_id' => $trainingMode->id,
        'training_start_date' => now()->addDays(15)->format('Y-m-d'),
        'training_end_date' => now()->addDays(18)->format('Y-m-d'),
        'status' => 'submitted',
    ]);

    $docType = \App\Models\NtcDocumentType::first();

    // Document: status is rejected but file still exists
    $document = \App\Models\NtcDocument::create([
        'ntc_report_id' => $ntcReport->id,
        'ntc_document_type_id' => $docType->id,
        'file_path' => 'dummy_ntc1.pdf',
        'original_filename' => 'test_ntc1.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_at' => now(),
        'status' => 'rejected',
    ]);

    $response = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'));

    $response->assertStatus(200);
    $html = $response->getContent();

    // The NTC report status should show "Submitted" and NOT "Requires Re-submission"
    expect($html)->toContain('Submitted');
    expect($html)->not->toContain('Requires Re-submission');
    expect($html)->not->toContain('Action Required');
});



