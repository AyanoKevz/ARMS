<?php

use App\Models\Accreditation;
use App\Models\AdminProfile;
use App\Models\AdminRole;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\Division;
use App\Models\Instructor;
use App\Models\InstructorCredential;
use App\Models\InstructorPerson;
use App\Models\NtcReport;
use App\Models\NtcTrainingMode;
use App\Models\NtcTrainingType;
use App\Models\PostTrainingDraft;
use App\Models\PostTrainingReport;
use App\Models\PtrDocumentType;
use App\Models\PtrParticipant;
use App\Models\Role;
use App\Models\User;
use App\Support\ApplicantStoragePath;
use App\Support\PhLocations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Mail::fake();

    $this->fatproTypeId = \App\Models\AccreditationType::firstOrCreate(
        ['name' => 'First Aid Training Providers']
    )->id;
});

/** A FATPro whose training has been held and whose NTC is acknowledged. */
function ptrFixture($suffix = '01'): array
{
    $applicantRole = Role::firstOrCreate(['name' => 'Applicant']);

    $applicant = User::forceCreate([
        'email'        => "ptr_dir_{$suffix}@example.com",
        'password'     => bcrypt('password'),
        'role_id'      => $applicantRole->id,
        'profile_type' => 'Organization',
    ]);

    $typeId = \App\Models\AccreditationType::firstOrCreate(
        ['name' => 'First Aid Training Providers']
    )->id;

    $application = Application::create([
        'user_id'               => $applicant->id,
        'accreditation_type_id' => $typeId,
        'application_type'      => 'new',
        'tracking_number'       => "ARMS-DIR-{$suffix}",
    ]);

    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id'      => ApplicationStatus::firstOrCreate(['name' => 'Approved'])->id,
    ]);

    $accreditation = Accreditation::create([
        'user_id'               => $applicant->id,
        'application_id'        => $application->id,
        'accreditation_type_id' => $typeId,
        'accreditation_number'  => "FATPRO-DIR-{$suffix}",
        'date_of_accreditation' => now()->subMonths(2)->format('Y-m-d'),
        'validity_date'         => now()->addYears(2)->format('Y-m-d'),
        'status'                => 'active',
    ]);

    // An instructor who clears every eligibility gate: approved record,
    // approved CV, and credentials that outlast the training.
    $person = InstructorPerson::create([
        'user_id'    => $applicant->id,
        'first_name' => 'Rosa',
        'last_name'  => "Trainer{$suffix}",
    ]);

    $instructor = Instructor::create([
        'user_id'              => $applicant->id,
        'application_id'       => $application->id,
        'instructor_person_id' => $person->id,
        'first_name'           => 'Rosa',
        'last_name'            => "Trainer{$suffix}",
        'cv_path'              => 'dummy_files/cv.pdf',
        'cv_status'            => 'approved',
        'status'               => 'approved',
    ]);

    foreach (['EMS', 'TM1', 'NTTC'] as $type) {
        InstructorCredential::create([
            'instructor_id' => $instructor->id,
            'type'          => $type,
            'number'        => "{$type}-{$suffix}",
            'validity_date' => now()->addYear()->format('Y-m-d'),
            'status'        => 'approved',
        ]);
    }

    // Training already concluded, so a report is owed.
    $ntc = NtcReport::create([
        'accreditation_id'     => $accreditation->id,
        'ntc_training_type_id' => NtcTrainingType::first()->id,
        'ntc_training_mode_id' => NtcTrainingMode::first()->id,
        'training_start_date'  => now()->subDays(10)->format('Y-m-d'),
        'training_end_date'    => now()->subDays(8)->format('Y-m-d'),
        'status'               => 'acknowledged',
        'acknowledged_at'      => now()->subDays(20),
    ]);

    $ntc->syncTrainingDates([
        1 => [now()->subDays(10)->format('Y-m-d')],
        2 => [now()->subDays(8)->format('Y-m-d')],
    ]);

    $ntc->instructors()->sync([$person->id]);

    return [$applicant, $ntc->refresh(), $instructor];
}

/**
 * Everything a submission posts apart from the Directory: the four PDF
 * attachments, the instructors carried over from the NTC, and the
 * report-level training video link.
 *
 * Requirement 2 is no longer a file, so it comes from the NTC's declared
 * roster rather than from the loop over file types.
 */
function ptrSubmissionFields(NtcReport $ntc): array
{
    $fields = [];

    foreach (PtrDocumentType::where('entry_type', 'file')->get() as $type) {
        $fields[$type->inputName()] = UploadedFile::fake()
            ->create($type->code . '.pdf', 80, 'application/pdf');
    }

    $fields['instructor_ids'] = $ntc->instructors->pluck('id')->all();

    // Report-level, and required on every submission.
    $fields['training_video_url'] = 'https://drive.google.com/file/d/training-recording';

    return $fields;
}

test('the Directory of Participants is seeded as encoded, not as a spreadsheet', function () {
    $directory = PtrDocumentType::where('code', 'DIRECTORY')->first();

    expect($directory->entry_type)->toBe('encoded');
    expect($directory->isEncoded())->toBeTrue();
    expect($directory->accepted_extensions)->toBeNull();

    // Three plain uploads are left: the roster and the test scores stopped
    // being uploads.
    expect(PtrDocumentType::where('entry_type', 'file')->count())->toBe(3);
    $instructors = PtrDocumentType::where('code', 'INSTRUCTORS')->first();
    expect($instructors->entry_type)->toBe('roster');
    expect($instructors->isRoster())->toBeTrue();
    expect($instructors->isFile())->toBeFalse();
    expect($instructors->accepted_extensions)->toBeNull();
});

test('a participant ID picture is staged on its own and capped at 5 MB', function () {
    [$applicant] = ptrFixture('02');

    // Under the cap: accepted, and a token comes back.
    $ok = $this->actingAs($applicant)->post(route('applicant.post_training.participant_photo'), [
        'photo' => UploadedFile::fake()->image('id.jpg', 300, 300)->size(400),
    ]);

    $ok->assertOk();
    expect($ok->json('token'))->toMatch('/^[A-Za-z0-9\-]+\.jpg$/');

    // Over the cap: rejected.
    $tooBig = $this->actingAs($applicant)->post(route('applicant.post_training.participant_photo'), [
        'photo' => UploadedFile::fake()->image('huge.jpg')->size(6000),
    ]);

    $tooBig->assertSessionHasErrors('photo');

    // Wrong format: rejected.
    $wrongType = $this->actingAs($applicant)->post(route('applicant.post_training.participant_photo'), [
        'photo' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
    ]);

    $wrongType->assertSessionHasErrors('photo');
});

test('a report submits with encoded participants and no Directory file', function () {
    [$applicant, $ntc] = ptrFixture('03');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    $participants = [[
        'certificate_number' => 'CERT-001',
        'last_name'          => 'Dela Cruz',
        'first_name'         => 'Juan',
        'middle_name'        => 'Santos',
        'suffix'             => 'Jr.',
        'sex'                => 'Male',
        'age'                => '34',
        'company'            => 'Acme Manufacturing',
        'position'           => 'Safety Officer',
        'company_city'       => 'Quezon City',
        'company_region'     => 'NCR',
        'industry'           => 'Manufacturing',
        'total_workers'      => '250',
        'company_email'      => 'hr@acme.test',
        'personal_email'     => 'juan@example.test',
        'mobile_no'          => '09171234567',
        'company_landline'   => '0281234567',
        'mode_of_training'   => 'Face to Face',
        'batch_no'           => '2026-01',
        'pre_test_score'     => '9/20',
        'post_test_score'    => '17/20',
        'catch_up'           => 'No',
        'photo_token'        => $token,
        'photo_name'         => 'id.jpg',
    ]];

    $response = $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => json_encode($participants)])
    );

    $response->assertRedirect(route('applicant.ntc.index'));
    $response->assertSessionHas('success');

    $report = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();
    expect($report)->not->toBeNull();

    // One row per encoded participant, with the values as typed.
    expect($report->participants)->toHaveCount(1);

    $row = $report->participants->first();
    expect($row->certificate_number)->toBe('CERT-001');
    expect($row->fullName())->toBe('Juan Santos Dela Cruz Jr.');
    expect($row->age)->toBe(34);
    expect($row->total_workers)->toBe(250);
    expect($row->status)->toBe('pending');
    expect($row->hasIdPicture())->toBeTrue();

    // Requirement 5's scores are kept on the row, as typed.
    expect($row->pre_test_score)->toBe('9/20');
    expect($row->post_test_score)->toBe('17/20');
    expect($row->scoreSheetName())->toBe('Juan Santos Dela Cruz');

    // And requirement 5 is a fileless section of its own.
    $scores = $report->scoresDocument();
    expect($scores)->not->toBeNull();
    expect($scores->file_path)->toBeNull();

    // All six sections exist; the Directory carries no file.
    expect($report->documents)->toHaveCount(7);

    $directory = $report->directoryDocument();
    expect($directory)->not->toBeNull();
    expect($directory->file_path)->toBeNull();
    expect($directory->original_filename)->toBeNull();

    // A fileless Directory must never be mistaken for a declined upload.
    expect($report->declinedDocuments())->toHaveCount(0);
});

test('submission is refused when a participant has no ID picture', function () {
    [$applicant, $ntc] = ptrFixture('04');

    $participants = [[
        'certificate_number' => 'CERT-002',
        'last_name'          => 'Reyes',
        'first_name'         => 'Maria',
        'sex'                => 'Female',
        'age'                => '29',
        'company'            => 'Beta Corp',
        'position'           => 'Nurse',
        'company_city'       => 'Cebu City',
        'company_region'     => 'Region VII',
        'industry'           => 'Healthcare',
        'mobile_no'          => '09181234567',
        'mode_of_training'   => 'Face to Face',
        ...ptrRequiredExtras(),
        'photo_token'        => '',
    ]];

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => json_encode($participants)])
    )->assertSessionHasErrors('participants.0.photo_token');

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
});

test('a certificate number cannot repeat inside one Directory', function () {
    [$applicant, $ntc] = ptrFixture('05');

    $token = fn () => $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(150),
        ])->json('token');

    $base = [
        'sex'              => 'Male',
        'age'              => '40',
        'company'          => 'Gamma Inc',
        'position'         => 'Supervisor',
        'company_city'     => 'Davao City',
        'company_region'   => 'Region XI',
        'industry'         => 'Logistics',
        'mobile_no'        => '09191234567',
        'mode_of_training' => 'Face to Face',
        ...ptrRequiredExtras(),
    ];

    $participants = [
        array_merge($base, [
            'certificate_number' => 'DUPE-1',
            'last_name' => 'Cruz', 'first_name' => 'Pedro',
            'photo_token' => $token(),
        ]),
        array_merge($base, [
            'certificate_number' => 'dupe-1', // same number, different case
            'last_name' => 'Lim', 'first_name' => 'Ana',
            'photo_token' => $token(),
        ]),
    ];

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => json_encode($participants)])
    )->assertSessionHasErrors('participants.1.certificate_number');
});

test('declining the Directory as a whole declines every participant', function () {
    [$applicant, $ntc] = ptrFixture('06');

    $token = fn () => $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(150),
        ])->json('token');

    $base = [
        'sex'              => 'Female',
        'age'              => '31',
        'company'          => 'Delta Co',
        'position'         => 'Officer',
        'company_city'     => 'Iloilo City',
        'company_region'   => 'Region VI',
        'industry'         => 'Retail',
        'mobile_no'        => '09201234567',
        'mode_of_training' => 'Face to Face',
        ...ptrRequiredExtras(),
    ];

    $participants = [
        array_merge($base, ['certificate_number' => 'C-1', 'last_name' => 'Uno', 'first_name' => 'A', 'photo_token' => $token()]),
        array_merge($base, ['certificate_number' => 'C-2', 'last_name' => 'Dos', 'first_name' => 'B', 'photo_token' => $token()]),
    ];

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => json_encode($participants)])
    )->assertSessionHas('success');

    $report = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();

    // A Training Evaluator to judge the rows.
    $evaluator = User::forceCreate([
        'email'        => 'ptr_dir_evaluator@example.com',
        'password'     => bcrypt('password'),
        'role_id'      => Role::firstOrCreate(['name' => 'Admin'])->id,
        'profile_type' => 'Individual',
    ]);

    AdminProfile::create([
        'user_id'       => $evaluator->id,
        'division_id'   => Division::firstOrCreate(['name' => 'HCD'])->id,
        'first_name'    => 'Tess',
        'last_name'     => 'Evaluator',
        'position'      => 'LSO III',
        'admin_role_id' => AdminRole::firstOrCreate(['name' => 'Training Evaluator'])->id,
    ]);

    $report->load('documents.documentType');
    $directoryId = $report->directoryDocument()->id;

    // Accept every attachment; decline the Directory as one section, with remarks.
    $evaluations = $report->documents->map(fn ($d) => $d->id === $directoryId
        ? ['id' => $d->id, 'status' => 'rejected', 'remarks' => 'Row 2 has the wrong certificate number.']
        : ['id' => $d->id, 'status' => 'approved', 'remarks' => '']
    )->values()->all();

    $this->actingAs($evaluator)->postJson(
        route('admin.hcd.reports.post_training.finalize_evaluation', $report->id),
        ['evaluations' => $evaluations]
    )->assertOk()->assertJson(['has_rejections' => true]);

    $report->refresh()->load(['documents.documentType', 'participants']);

    $directory = $report->directoryDocument();
    expect($directory->status)->toBe('rejected');
    expect($directory->remarks)->toBe('Row 2 has the wrong certificate number.');

    // Every participant is opened for correction, not just one.
    expect($report->hasDirectoryCorrections())->toBeTrue();
    expect($report->rejectedParticipants())->toHaveCount(2);

    // The declined rows keep their ID pictures: the FATPro edits them in place.
    expect($report->participants->every->hasIdPicture())->toBeTrue();

    // And the whole report is declined, not accepted.
    expect($report->status)->toBe('declined');
});

test('accepting the Directory as a whole accepts every participant', function () {
    [$applicant, $ntc] = ptrFixture('06b');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(150),
        ])->json('token');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrOneParticipant('C-ACC', $token)])
    )->assertSessionHas('success');

    $report = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();

    $evaluator = User::forceCreate([
        'email'        => 'ptr_dir_accepter@example.com',
        'password'     => bcrypt('password'),
        'role_id'      => Role::firstOrCreate(['name' => 'Admin'])->id,
        'profile_type' => 'Individual',
    ]);

    AdminProfile::create([
        'user_id'       => $evaluator->id,
        'division_id'   => Division::firstOrCreate(['name' => 'HCD'])->id,
        'first_name'    => 'Acc',
        'last_name'     => 'Evaluator',
        'position'      => 'LSO III',
        'admin_role_id' => AdminRole::firstOrCreate(['name' => 'Training Evaluator'])->id,
    ]);

    $this->actingAs($evaluator)->postJson(
        route('admin.hcd.reports.post_training.finalize_evaluation', $report->id),
        ['evaluations' => $report->documents->map(fn ($d) => [
            'id' => $d->id, 'status' => 'approved', 'remarks' => '',
        ])->values()->all()]
    )->assertOk();

    $report->refresh()->load('participants');

    expect($report->status)->toBe('accepted');
    expect($report->participants->every(fn ($p) => $p->status === 'approved'))->toBeTrue();
});

test('the FATPro corrects declined rows without re-uploading anything', function () {
    [$applicant, $ntc] = ptrFixture('07');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(150),
        ])->json('token');

    $participants = [[
        'certificate_number' => 'FIX-1',
        'last_name'          => 'Torres',
        'first_name'         => 'Luis',
        'sex'                => 'Male',
        'age'                => '45',
        'company'            => 'Epsilon Ltd',
        'position'           => 'Manager',
        'company_city'       => 'Baguio City',
        'company_region'     => 'CAR',
        'industry'           => 'Mining',
        'mobile_no'          => '09211234567',
        'mode_of_training'   => 'Face to Face',
        ...ptrRequiredExtras(),
        'photo_token'        => $token,
    ]];

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => json_encode($participants)])
    )->assertSessionHas('success');

    $report = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();
    $row = $report->participants->first();

    // Simulate the evaluator having declined this row.
    $row->update(['status' => 'rejected']);
    $report->directoryDocument()->update(['status' => 'rejected', 'remarks' => 'Wrong company.']);
    $report->update(['status' => 'declined']);

    $this->actingAs($applicant)->post(
        route('applicant.post_training.participants.update', $report->id),
        ['participants' => json_encode([[
            'id'                 => $row->id,
            'certificate_number' => 'FIX-1',
            'last_name'          => 'Torres',
            'first_name'         => 'Luis',
            'sex'                => 'Male',
            'age'                => '45',
            'company'            => 'Epsilon Philippines Ltd', // the correction
            'position'           => 'Manager',
            'company_city'       => 'Baguio City',
            'company_region'     => 'CAR',
            'industry'           => 'Mining',
            'mobile_no'          => '09211234567',
            'mode_of_training'   => 'Face to Face',
            ...ptrRequiredExtras(),
            'photo_token'        => '', // no replacement picture needed
        ]])]
    )->assertSessionHas('success');

    $row->refresh();
    expect($row->company)->toBe('Epsilon Philippines Ltd');
    expect($row->status)->toBe('pending');
    expect($row->hasIdPicture())->toBeTrue();

    $report->refresh()->load('documents.documentType');
    expect($report->status)->toBe('submitted');
    expect($report->directoryDocument()->status)->toBe('returned');
});

test('an approved participant cannot be edited by the FATPro', function () {
    [$applicant, $ntc] = ptrFixture('08');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(150),
        ])->json('token');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => json_encode([[
            'certificate_number' => 'LOCK-1',
            'last_name'          => 'Santos',
            'first_name'         => 'Rita',
            'sex'                => 'Female',
            'age'                => '38',
            'company'            => 'Zeta Group',
            'position'           => 'Trainer',
            'company_city'       => 'Makati City',
            'company_region'     => 'NCR',
            'industry'           => 'Services',
            'mobile_no'          => '09221234567',
            'mode_of_training'   => 'Face to Face',
            ...ptrRequiredExtras(),
            'photo_token'        => $token,
        ]])])
    );

    $report = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();
    $row = $report->participants->first();
    $row->update(['status' => 'approved']);

    $this->actingAs($applicant)->post(
        route('applicant.post_training.participants.update', $report->id),
        ['participants' => json_encode([[
            'id'                 => $row->id,
            'certificate_number' => 'TAMPERED',
            'last_name'          => 'Hacker',
            'first_name'         => 'Eve',
            'sex'                => 'Female',
            'age'                => '38',
            'company'            => 'Zeta Group',
            'position'           => 'Trainer',
            'company_city'       => 'Makati City',
            'company_region'     => 'NCR',
            'industry'           => 'Services',
            'mobile_no'          => '09221234567',
            'mode_of_training'   => 'Face to Face',
            ...ptrRequiredExtras(),
            'photo_token'        => '',
        ]])]
    );

    $row->refresh();
    expect($row->certificate_number)->toBe('LOCK-1');
    expect($row->status)->toBe('approved');
});

test('a staged picture belonging to another FATPro cannot be claimed', function () {
    [$attacker, $ntc] = ptrFixture('09');
    [$victim] = ptrFixture('10');

    // The victim stages a picture; the attacker tries to submit with its token.
    $victimToken = $this->actingAs($victim)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('victim.jpg')->size(150),
        ])->json('token');

    $this->actingAs($attacker)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => json_encode([[
            'certificate_number' => 'X-1',
            'last_name'          => 'Nobody',
            'first_name'         => 'No',
            'sex'                => 'Male',
            'age'                => '30',
            'company'            => 'X',
            'position'           => 'X',
            'company_city'       => 'Taguig City',
            'company_region'     => 'NCR',
            'industry'           => 'X',
            'mobile_no'          => '09000000000',
            'mode_of_training'   => 'Face to Face',
            ...ptrRequiredExtras(),
            'photo_token'        => $victimToken,
        ]])])
    )->assertSessionHasErrors('participants.0.photo_token');

    expect(PtrParticipant::count())->toBe(0);
});

test('a path traversal token is rejected outright', function () {
    [$applicant, $ntc] = ptrFixture('11');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => json_encode([[
            'certificate_number' => 'T-1',
            'last_name'          => 'Trav',
            'first_name'         => 'Ersal',
            'sex'                => 'Male',
            'age'                => '30',
            'company'            => 'X',
            'position'           => 'X',
            'company_city'       => 'Taguig City',
            'company_region'     => 'NCR',
            'industry'           => 'X',
            'mobile_no'          => '09000000000',
            'mode_of_training'   => 'Face to Face',
            ...ptrRequiredExtras(),
            'photo_token'        => '../../../.env',
        ]])])
    )->assertSessionHasErrors('participants.0.photo_token');
});

test('the applicant portal renders the encoding grid instead of a file dropzone', function () {
    [$applicant, $ntc] = ptrFixture('12');

    $response = $this->actingAs($applicant)->get(route('applicant.ntc.index'));

    $response->assertOk();
    $html = $response->getContent();

    // The grid, its bulk control and the single JSON field are all present.
    expect($html)->toContain('id="ptrDirTable"');
    expect($html)->toContain('id="ptrDirAddRows"');
    expect($html)->toContain('name="participants"');

    // Every Directory column is offered.
    foreach (['certificate_number', 'company_region', 'total_workers', 'mode_of_training', 'batch_no'] as $field) {
        expect($html)->toContain('data-field="' . $field . '"');
    }

    // And the Directory no longer asks for a spreadsheet.
    expect($html)->not->toContain('name="file_directory"');
    expect($html)->not->toContain('.xlsx');
});

test('the evaluator page judges the participant table with one Accept or Decline', function () {
    [$applicant, $ntc] = ptrFixture('13');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(150),
        ])->json('token');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => json_encode([[
            'certificate_number' => 'VIEW-1',
            'last_name'          => 'Bautista',
            'first_name'         => 'Carlo',
            'sex'                => 'Male',
            'age'                => '27',
            'company'            => 'Omega Works',
            'position'           => 'Technician',
            'company_city'       => 'Pasig City',
            'company_region'     => 'NCR',
            'industry'           => 'Construction',
            'mobile_no'          => '09231234567',
            'mode_of_training'   => 'Face to Face',
            ...ptrRequiredExtras(),
            'photo_token'        => $token,
        ]])])
    );

    $report = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();

    $evaluator = User::forceCreate([
        'email'        => 'ptr_dir_viewer@example.com',
        'password'     => bcrypt('password'),
        'role_id'      => Role::firstOrCreate(['name' => 'Admin'])->id,
        'profile_type' => 'Individual',
    ]);

    AdminProfile::create([
        'user_id'       => $evaluator->id,
        'division_id'   => Division::firstOrCreate(['name' => 'HCD'])->id,
        'first_name'    => 'View',
        'last_name'     => 'Evaluator',
        'position'      => 'LSO III',
        'admin_role_id' => AdminRole::firstOrCreate(['name' => 'Training Evaluator'])->id,
    ]);

    $response = $this->actingAs($evaluator)
        ->get(route('admin.hcd.reports.post_training.show', $report->id));

    $response->assertOk();
    $html = $response->getContent();

    $directoryId = $report->load('documents.documentType')->directoryDocument()->id;

    expect($html)->toContain('Bautista');
    expect($html)->toContain('VIEW-1');

    // One verdict for the whole Directory, with the same remarks box as the
    // instructor list — no per-row verdict column.
    expect($html)->toContain("setNtcDocStatus({$directoryId}, 'approved')");
    expect($html)->toContain("setNtcDocStatus({$directoryId}, 'rejected')");
    expect($html)->toContain('name="evaluations[' . $directoryId . '][remarks]"');
    expect($html)->not->toContain('ptr-dir-verdict');
    expect($html)->not->toContain('ptr-participant-status-');
});

test('the submission modal is a step-by-step wizard listing every requirement', function () {
    [$applicant, $ntc] = ptrFixture('14');

    $html = $this->actingAs($applicant)->get(route('applicant.ntc.index'))->getContent();

    $types = PtrDocumentType::orderBy('sort_order')->get();

    // The card at the top names all six requirements up front, not just the
    // current one, and sits above the step rather than beside it.
    expect($html)->toContain('ptr-reqs-grid');
    expect($html)->toContain('ptr-req');
    expect($html)->toContain('Required Items &amp; Instructions');
    foreach ($types as $type) {
        expect($html)->toContain(e($type->name));
    }

    // One panel per requirement.
    foreach ($types as $index => $type) {
        expect($html)->toContain('data-step="' . ($index + 1) . '"');
    }

    // The Directory leads, and the five attachments follow it.
    expect($html)->toContain('data-step="1"');
    expect($html)->toMatch('/data-step="1"[^>]*data-kind="directory"/');

    // Only the first step starts visible.
    expect(substr_count($html, 'ptr-step-panel is-active'))->toBe(1);

    // Back / Next / Submit rather than one long scroll.
    expect($html)->toContain('id="ptrStepBack"');
    expect($html)->toContain('id="ptrStepNext"');
    expect($html)->toContain('id="ptrStepSubmit"');

    // The decorative flag icon is gone from the modal title.
    expect($html)->not->toContain('fa-flag-checkered text-warning');
});

/* ══════════════════════════════════════════════════════════════
   Requirement 2 — List of Instructors Who Conducted the Training

   No longer an attachment. The list arrives from the parent NTC and
   the FATPro either proceeds or amends it, so what is tested here is
   the carry-over, the amendment, and who may be named.
   ══════════════════════════════════════════════════════════════ */

/** One valid encoded participant, so the Directory step is satisfied. */
function ptrOneParticipant(string $certificate, string $photoToken): string
{
    return json_encode([[
        'certificate_number' => $certificate,
        'photo_token'        => $photoToken,
        'last_name'          => 'Reyes',
        'first_name'         => 'Ana',
        'sex'                => 'Female',
        'age'                => 30,
        'company'            => 'Acme',
        'position'           => 'Nurse',
        'company_city'       => 'Quezon City',
        'company_region'     => 'NCR',
        'industry'           => 'Manufacturing',
        'mobile_no'          => '09171234567',
        'mode_of_training'   => 'Face to Face',
        ...ptrRequiredExtras(),
    ]]);
}

test('the instructors carry over from the NTC and are stored on the report', function () {
    [$applicant, $ntc, $instructor] = ptrFixture('20');

    $photoToken = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrOneParticipant('CERT-20', $photoToken)])
    )->assertSessionHas('success');

    $report = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();

    expect($report->instructors->pluck('id')->all())->toBe([$instructor->instructor_person_id]);

    // The NTC keeps its own copy: the report is a snapshot, not a view onto it.
    expect($ntc->fresh()->instructors->pluck('id')->all())->toBe([$instructor->instructor_person_id]);
});

test('the instructor section gets a document row carrying no file', function () {
    [$applicant, $ntc] = ptrFixture('21');

    $photoToken = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrOneParticipant('CERT-21', $photoToken)])
    )->assertSessionHas('success');

    $report  = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();
    $section = $report->instructorsDocument();

    // All seven requirements are accounted for, the roster among them.
    expect($report->documents)->toHaveCount(7);
    expect($section)->not->toBeNull();
    expect($section->file_path)->toBeNull();
    expect($section->status)->toBe('pending');

    // It must not be mistaken for the Directory, which is keyed off isEncoded().
    expect($report->directoryDocument()->id)->not->toBe($section->id);
});

test('a report naming no instructors is rejected', function () {
    [$applicant, $ntc] = ptrFixture('22');

    $photoToken = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    $fields = ptrSubmissionFields($ntc);
    $fields['instructor_ids'] = [];

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge($fields, ['participants' => ptrOneParticipant('CERT-22', $photoToken)])
    )->assertSessionHasErrors('instructor_ids');

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
});

test('an instructor declared on the NTC stays nameable after their credentials lapse', function () {
    [$applicant, $ntc, $instructor] = ptrFixture('23');

    $photoToken = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    // They taught the course; a lapse in the weeks since does not unmake that.
    $instructor->credentials()->update(['status' => 'expired']);

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrOneParticipant('CERT-23', $photoToken)])
    )->assertSessionHas('success');

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->first()
        ->instructors->pluck('id')->all())->toBe([$instructor->instructor_person_id]);
});

test('an instructor from another FATPro cannot be added to the report', function () {
    [$applicant, $ntc] = ptrFixture('24');

    $photoToken = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');
    [, , $outsider]    = ptrFixture('25');

    $fields = ptrSubmissionFields($ntc);
    $fields['instructor_ids'] = [$outsider->instructor_person_id];

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge($fields, ['participants' => ptrOneParticipant('CERT-24', $photoToken)])
    )->assertSessionHasErrors('instructor_ids');

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
});

test('a declined instructor list is corrected by re-choosing, not re-uploading', function () {
    [$applicant, $ntc, $instructor] = ptrFixture('26');

    $photoToken = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrOneParticipant('CERT-26', $photoToken)])
    )->assertSessionHas('success');

    $report  = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();
    $section = $report->instructorsDocument();
    $section->update(['status' => 'rejected', 'remarks' => 'Wrong person listed.']);

    expect($report->fresh()->hasInstructorCorrections())->toBeTrue();

    // A file-based re-upload never covers it: it is not a declined document.
    expect($report->fresh()->declinedDocuments()->pluck('id')->all())->not->toContain($section->id);

    // A second eligible instructor, named on the correction.
    $replacementPerson = InstructorPerson::create([
        'user_id'    => $applicant->id,
        'first_name' => 'Mila',
        'last_name'  => 'Santos',
    ]);

    $replacement = Instructor::create([
        'user_id'              => $applicant->id,
        'application_id'       => $instructor->application_id,
        'instructor_person_id' => $replacementPerson->id,
        'first_name'           => 'Mila',
        'last_name'            => 'Santos',
        'cv_path'              => 'dummy_files/cv.pdf',
        'cv_status'            => 'approved',
        'status'               => 'approved',
    ]);

    foreach (['EMS', 'TM1', 'NTTC'] as $type) {
        InstructorCredential::create([
            'instructor_id' => $replacement->id,
            'type'          => $type,
            'number'        => "{$type}-26B",
            'validity_date' => now()->addYear()->format('Y-m-d'),
            'status'        => 'approved',
        ]);
    }

    $this->actingAs($applicant)->post(
        route('applicant.post_training.instructors.update', $report->id),
        ['instructor_ids' => [$replacementPerson->id]]
    )->assertSessionHas('success');

    $report->refresh()->load('instructors');

    expect($report->instructors->pluck('id')->all())->toBe([$replacementPerson->id]);
    expect($report->instructorsDocument()->status)->toBe('returned');
    expect($report->instructorsDocument()->remarks)->toBeNull();

    // The NTC still records who was originally declared.
    expect($ntc->fresh()->instructors->pluck('id')->all())->toBe([$instructor->instructor_person_id]);
});

test('the wizard renders the instructor step as a roster, not a dropzone', function () {
    [$applicant] = ptrFixture('27');

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-kind="roster"');
    expect($html)->toContain('data-ptr-instructors');
    expect($html)->not->toContain('name="file_instructors"');
});

test('a participant must have a real sex, not the placeholder or anything else', function () {
    [$applicant, $ntc] = ptrFixture('28');

    $photoToken = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    $row = function (string $sex) use ($photoToken) {
        return json_encode([[
            'certificate_number' => 'CERT-28',
            'photo_token'        => $photoToken,
            'last_name'          => 'Reyes',
            'first_name'         => 'Ana',
            'sex'                => $sex,
            'age'                => 30,
            'company'            => 'Acme',
            'position'           => 'Nurse',
            'company_city'       => 'Quezon City',
            'company_region'     => 'NCR',
            'industry'           => 'Manufacturing',
            'mobile_no'          => '09171234567',
            'mode_of_training'   => 'Face to Face',
            ...ptrRequiredExtras(),
        ]]);
    };

    // The placeholder posts as an empty string.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => $row('')])
    )->assertSessionHasErrors('participants.0.sex');

    // And nothing outside the two the form offers gets through either.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => $row('Unspecified')])
    )->assertSessionHasErrors('participants.0.sex');

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();

    // Female is accepted, so the rule is not simply refusing everything.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => $row('Female')])
    )->assertSessionHas('success');

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->first()
        ->participants->first()->sex)->toBe('Female');
});

test('the directory offers the sex placeholder as a prompt, not a choice', function () {
    [$applicant] = ptrFixture('29');

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('<option value="" disabled selected>— Select —</option>');
});

/* ══════════════════════════════════════════════════════════════
   Corrections — all three kinds, in one submission

   A report is sent back in three different ways and any
   combination can be outstanding at once.
   ══════════════════════════════════════════════════════════════ */

/** A filed report with one participant, ready to be sent back. */
function ptrFiledReport(string $suffix): array
{
    [$applicant, $ntc, $instructor] = ptrFixture($suffix);

    $photoToken = test()->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    test()->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrOneParticipant('CERT-' . $suffix, $photoToken)])
    )->assertSessionHas('success');

    $report = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();

    return [$applicant, $ntc, $instructor, $report];
}

/** The fields a corrected participant row must carry. */
function ptrCorrectedRow(PtrParticipant $participant, array $overrides = []): array
{
    return array_merge([
        'id'                 => $participant->id,
        'certificate_number' => $participant->certificate_number,
        'last_name'          => $participant->last_name,
        'first_name'         => $participant->first_name,
        'sex'                => $participant->sex,
        'age'                => $participant->age,
        'company'            => $participant->company,
        'position'           => $participant->position,
        'company_city'       => $participant->company_city,
        'company_region'     => $participant->company_region,
        'industry'           => $participant->industry,
        'mobile_no'          => $participant->mobile_no,
        'mode_of_training'   => $participant->mode_of_training,
        'total_workers'      => $participant->total_workers,
        'company_email'      => $participant->company_email,
        'personal_email'     => $participant->personal_email,
        'company_landline'   => $participant->company_landline,
        'batch_no'           => $participant->batch_no,
        'catch_up'           => $participant->is_catch_up ? 'Yes' : 'No',
        'catch_up_ntc_report_id' => $participant->catch_up_ntc_report_id,
        'photo_token'        => '',
    ], $overrides);
}

test('a declined participant row is corrected in place, keeping its picture', function () {
    [$applicant, , , $report] = ptrFiledReport('50');

    $participant = $report->participants->first();
    $picture     = $participant->id_picture_path;

    $participant->update(['status' => 'rejected']);
    $report->directoryDocument()->update(['status' => 'rejected', 'remarks' => 'Wrong company.']);

    expect($report->fresh()->needsCorrections())->toBeTrue();

    $this->actingAs($applicant)->post(
        route('applicant.post_training.corrections', $report->id),
        ['participants' => json_encode([ptrCorrectedRow($participant, ['company' => 'Corrected Corp'])])]
    )->assertSessionHas('success');

    $participant->refresh();

    expect($participant->company)->toBe('Corrected Corp');
    expect($participant->status)->toBe('pending');

    // No replacement picture was sent, so the original must survive.
    expect($participant->id_picture_path)->toBe($picture);

    // The section goes back in front of the evaluator.
    expect($report->fresh()->directoryDocument()->status)->toBe('returned');
    expect($report->fresh()->directoryDocument()->remarks)->toBeNull();
});

test('an approved participant is untouched by a correction that names it', function () {
    [$applicant, , , $report] = ptrFiledReport('51');

    $participant = $report->participants->first();
    $participant->update(['status' => 'approved']);

    // Nothing is outstanding, so the whole submission is refused.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.corrections', $report->id),
        ['participants' => json_encode([ptrCorrectedRow($participant, ['company' => 'Sneaky Corp'])])]
    )->assertSessionHasErrors('error');

    expect($participant->fresh()->company)->not->toBe('Sneaky Corp');
    expect($participant->fresh()->status)->toBe('approved');
});

test('a declined instructor roster is corrected through the same submission', function () {
    [$applicant, $ntc, $instructor, $report] = ptrFiledReport('52');

    $report->instructorsDocument()->update(['status' => 'rejected', 'remarks' => 'Wrong person.']);

    expect($report->fresh()->needsCorrections())->toBeTrue();
    expect($report->fresh()->declinedSectionCount())->toBe(1);

    $this->actingAs($applicant)->post(
        route('applicant.post_training.corrections', $report->id),
        ['instructor_ids' => [$instructor->instructor_person_id]]
    )->assertSessionHas('success');

    expect($report->fresh()->instructorsDocument()->status)->toBe('returned');
});

test('every declined section is corrected together in one submission', function () {
    [$applicant, $ntc, $instructor, $report] = ptrFiledReport('53');

    // All three kinds at once: an attachment, the Directory, the roster.
    $attachment = $report->documents->first(fn ($d) => $d->documentType->isFile());
    $attachment->update(['status' => 'rejected', 'file_path' => null, 'remarks' => 'Illegible.']);

    $participant = $report->participants->first();
    $participant->update(['status' => 'rejected']);
    $report->directoryDocument()->update(['status' => 'rejected']);

    $report->instructorsDocument()->update(['status' => 'rejected']);

    $report->refresh()->load(['documents.documentType', 'participants', 'instructors']);

    expect($report->declinedSectionCount())->toBe(3);

    $this->actingAs($applicant)->post(
        route('applicant.post_training.corrections', $report->id),
        [
            'files'          => [$attachment->id => UploadedFile::fake()->create('fixed.pdf', 90, 'application/pdf')],
            'participants'   => json_encode([ptrCorrectedRow($participant, ['position' => 'Corrected Officer'])]),
            'instructor_ids' => [$instructor->instructor_person_id],
        ]
    )->assertSessionHas('success');

    $report->refresh();

    expect($attachment->fresh()->status)->toBe('returned');
    expect($attachment->fresh()->file_path)->not->toBeNull();
    expect($participant->fresh()->position)->toBe('Corrected Officer');
    expect($report->directoryDocument()->status)->toBe('returned');
    expect($report->instructorsDocument()->status)->toBe('returned');
    expect($report->status)->toBe('submitted');

    // Nothing is left outstanding.
    expect($report->needsCorrections())->toBeFalse();
});

test('a correction that leaves a declined attachment empty is refused outright', function () {
    [$applicant, , $instructor, $report] = ptrFiledReport('54');

    $attachment = $report->documents->first(fn ($d) => $d->documentType->isFile());
    $attachment->update(['status' => 'rejected', 'file_path' => null]);

    $participant = $report->participants->first();
    $participant->update(['status' => 'rejected']);
    $report->directoryDocument()->update(['status' => 'rejected']);

    // The participant half is valid, the file half is missing — so nothing at
    // all is written, rather than half the report being corrected.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.corrections', $report->id),
        ['participants' => json_encode([ptrCorrectedRow($participant, ['position' => 'Should Not Stick'])])]
    )->assertSessionHasErrors("files.{$attachment->id}");

    expect($participant->fresh()->position)->not->toBe('Should Not Stick');
    expect($participant->fresh()->status)->toBe('rejected');
});

test('a report with nothing outstanding cannot be corrected', function () {
    [$applicant, , $instructor, $report] = ptrFiledReport('55');

    expect($report->needsCorrections())->toBeFalse();

    $this->actingAs($applicant)->post(
        route('applicant.post_training.corrections', $report->id),
        ['instructor_ids' => [$instructor->instructor_person_id]]
    )->assertSessionHasErrors('error');
});

test('another FATPro cannot correct a report that is not theirs', function () {
    [, , $instructor, $report] = ptrFiledReport('56');
    [$outsider] = ptrFixture('57');

    $report->instructorsDocument()->update(['status' => 'rejected']);

    $this->actingAs($outsider)->post(
        route('applicant.post_training.corrections', $report->id),
        ['instructor_ids' => [$instructor->instructor_person_id]]
    )->assertForbidden();

    expect($report->fresh()->instructorsDocument()->status)->toBe('rejected');
});

test('the portal offers one dialog carrying every declined section', function () {
    [$applicant, , , $report] = ptrFiledReport('58');

    $attachment = $report->documents->first(fn ($d) => $d->documentType->isFile());
    $attachment->update(['status' => 'rejected', 'file_path' => null, 'remarks' => 'Illegible.']);

    $participant = $report->participants->first();
    $participant->update(['status' => 'rejected']);
    $report->directoryDocument()->update(['status' => 'rejected', 'remarks' => 'Wrong company.']);

    $report->instructorsDocument()->update(['status' => 'rejected', 'remarks' => 'Wrong person.']);

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    // One button, one dialog, three sections inside it.
    expect($html)->toContain('Correct 3 Declined Items');
    expect($html)->toContain('ptrReuploadModal-' . $report->id);
    expect($html)->toContain('ptr-corrections-form');

    // The attachment keeps its drop zone…
    expect($html)->toContain('files[' . $attachment->id . ']');
    // …the Directory becomes editable rows…
    expect($html)->toContain('data-participant-id="' . $participant->id . '"');
    expect($html)->toContain('data-correction-payload');
    // …and the roster comes back as checkboxes.
    expect($html)->toContain('data-correction-instructors');

    // Each section carries the remark that sent it back.
    expect($html)->toContain('Illegible.');
    expect($html)->toContain('Wrong company.');
    expect($html)->toContain('Wrong person.');

    // A correction is held to the same formats as a first filing: the address
    // is picked, not typed, and the two numbers carry their shapes. The region
    // leads here too, and each picker is pointed at what was already stored.
    expect(strpos($html, 'data-field="company_region" data-ph-region'))
        ->toBeLessThan(strpos($html, 'data-field="company_city" data-ph-city'));

    expect($html)->toContain('data-value="' . $participant->company_region . '"');
    expect($html)->toContain('data-value="' . $participant->company_city . '"');
    expect($html)->toContain('data-ph-mobile');
    expect($html)->toContain('data-ph-landline');
});

test('a submission needs a valid link to the training video', function () {
    [$applicant, $ntc] = ptrFixture('60');

    $photoToken = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    $submit = function ($video) use ($applicant, $ntc, $photoToken) {
        $fields = ptrSubmissionFields($ntc);

        if ($video === null) {
            unset($fields['training_video_url']);
        } else {
            $fields['training_video_url'] = $video;
        }

        return $this->actingAs($applicant)->post(
            route('applicant.post_training.store', $ntc->id),
            array_merge($fields, ['participants' => ptrOneParticipant('CERT-60', $photoToken)])
        );
    };

    // A recording is required, and it has to be a link we can open.
    $submit(null)->assertSessionHasErrors('training_video_url');
    $submit('not a link at all')->assertSessionHasErrors('training_video_url');

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();

    $submit('https://drive.google.com/file/d/abc123')->assertSessionHas('success');

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->first()->training_video_url)
        ->toBe('https://drive.google.com/file/d/abc123');
});

test('the FATPro can leave a remark on the submission, and the evaluator sees it', function () {
    [$applicant, $ntc] = ptrFixture('61');

    $photoToken = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    $fields = ptrSubmissionFields($ntc);
    $fields['applicant_remarks'] = 'One participant withdrew on the second day.';

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge($fields, ['participants' => ptrOneParticipant('CERT-61', $photoToken)])
    )->assertSessionHas('success');

    $report = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();

    expect($report->applicant_remarks)->toBe('One participant withdrew on the second day.');

    // The evaluator's own remarks column is untouched by it.
    expect($report->remarks)->toBeNull();

    $evaluator = User::forceCreate([
        'email'        => 'ptr_remarks_viewer@example.com',
        'password'     => bcrypt('password'),
        'role_id'      => Role::firstOrCreate(['name' => 'Admin'])->id,
        'profile_type' => 'Individual',
    ]);

    AdminProfile::create([
        'user_id'       => $evaluator->id,
        'division_id'   => Division::firstOrCreate(['name' => 'HCD'])->id,
        'first_name'    => 'Tess',
        'last_name'     => 'Evaluator',
        'position'      => 'LSO III',
        'admin_role_id' => AdminRole::firstOrCreate(['name' => 'Training Evaluator'])->id,
    ]);

    $html = $this->actingAs($evaluator)
        ->get(route('admin.hcd.reports.post_training.show', $report->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('One participant withdrew on the second day.');
    expect($html)->toContain('Training Video');
    expect($html)->toContain($report->training_video_url);
});

test('the training video is requirement 7, with a section of its own', function () {
    $video = PtrDocumentType::where('code', 'VIDEO')->first();

    expect($video)->not->toBeNull();
    expect($video->sort_order)->toBe(7);
    expect($video->entry_type)->toBe('link');
    expect($video->stepKind())->toBe('link');

    // A link is not an upload, so it must not be swept into the file rules.
    expect($video->isFile())->toBeFalse();
    expect($video->isLink())->toBeTrue();

    // Still three PDF uploads; the video did not become a fourth.
    expect(PtrDocumentType::where('entry_type', 'file')->count())->toBe(3);
});

test('the video is the last required step and remarks closes the wizard', function () {
    [$applicant] = ptrFixture('62');

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    // Seven required items, then Remarks as an eighth, optional step.
    expect($html)->toContain('data-total="8"');
    expect($html)->toContain('data-kind="link"');
    expect($html)->toContain('name="training_video_url"');
    expect($html)->toContain('Step 7 of 8');

    expect($html)->toContain('data-kind="remarks"');
    expect($html)->toContain('Step 8 of 8');
    expect($html)->toContain('ptr_applicant_remarks');

    // The checklist lists what is REQUIRED, so Remarks is not on it: seven
    // numbered cards, reached by Next for the eighth step.
    expect(substr_count($html, 'data-goto='))->toBe(7);

    // It is a step now, not a block tacked on under the wizard.
    expect($html)->not->toContain('ptr-extra-details');
});

test('a declined video link is corrected by entering a new one', function () {
    [$applicant, , , $report] = ptrFiledReport('63');

    $section = $report->videoDocument();

    expect($section)->not->toBeNull();
    expect($section->file_path)->toBeNull();

    $section->update(['status' => 'rejected', 'remarks' => 'Link is private.']);
    $report->refresh();

    expect($report->hasVideoCorrections())->toBeTrue();
    expect($report->declinedSectionCount())->toBe(1);

    // It is corrected by re-entering, so it never reaches the re-upload list.
    expect($report->declinedDocuments()->pluck('id')->all())->not->toContain($section->id);

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-correction-video');
    expect($html)->toContain('Link is private.');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.corrections', $report->id),
        ['training_video_url' => 'https://drive.google.com/file/d/now-public']
    )->assertSessionHas('success');

    $report->refresh();

    expect($report->training_video_url)->toBe('https://drive.google.com/file/d/now-public');
    expect($report->videoDocument()->status)->toBe('returned');
    expect($report->videoDocument()->remarks)->toBeNull();
    expect($report->needsCorrections())->toBeFalse();
});

test('a corrected video link still has to be a usable URL', function () {
    [$applicant, , , $report] = ptrFiledReport('64');

    $report->videoDocument()->update(['status' => 'rejected']);

    $this->actingAs($applicant)->post(
        route('applicant.post_training.corrections', $report->id),
        ['training_video_url' => 'still not a link']
    )->assertSessionHasErrors('training_video_url');

    expect($report->fresh()->videoDocument()->status)->toBe('rejected');
});

/* ══════════════════════════════════════════════════════════════
   Drafts — an unfinished report survives between sittings
   ══════════════════════════════════════════════════════════════ */

test('an unfinished report is kept and handed back on the next visit', function () {
    [$applicant, $ntc] = ptrFixture('70');

    $this->actingAs($applicant)->postJson(
        route('applicant.post_training.draft.save', $ntc->id),
        ['payload' => [
            'training_video_url' => 'https://drive.google.com/file/d/half-done',
            'applicant_remarks'  => 'Still encoding the directory.',
            'instructor_ids'     => $ntc->instructors->pluck('id')->all(),
            'participants'       => [['certificate_number' => 'CERT-70', 'last_name' => 'Reyes']],
            'step'               => 3,
        ]]
    )->assertOk()->assertJson(['ok' => true]);

    $draft = PostTrainingDraft::where('ntc_report_id', $ntc->id)->first();

    expect($draft)->not->toBeNull();
    expect($draft->user_id)->toBe($applicant->id);
    expect($draft->payload['training_video_url'])->toBe('https://drive.google.com/file/d/half-done');
    expect($draft->payload['step'])->toBe(3);

    // No report has been filed — a draft is not a submission.
    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();

    // And the portal hands it straight back to the dialog.
    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-draft=');
    expect($html)->toContain('half-done');
    expect($html)->toContain('ptrSaveStatus');
});

test('saving a draft again replaces it rather than piling up', function () {
    [$applicant, $ntc] = ptrFixture('71');

    foreach (['first pass', 'second pass'] as $note) {
        $this->actingAs($applicant)->postJson(
            route('applicant.post_training.draft.save', $ntc->id),
            ['payload' => ['applicant_remarks' => $note]]
        )->assertOk();
    }

    expect(PostTrainingDraft::where('ntc_report_id', $ntc->id)->count())->toBe(1);
    expect(PostTrainingDraft::where('ntc_report_id', $ntc->id)->first()->payload['applicant_remarks'])
        ->toBe('second pass');
});

test('a draft belongs to its FATPro and nobody else', function () {
    [, $ntc]     = ptrFixture('72');
    [$outsider]  = ptrFixture('73');

    $this->actingAs($outsider)->postJson(
        route('applicant.post_training.draft.save', $ntc->id),
        ['payload' => ['applicant_remarks' => 'not mine']]
    )->assertForbidden();

    expect(PostTrainingDraft::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
});

test('a document staged into a draft satisfies its requirement on submit', function () {
    [$applicant, $ntc] = ptrFixture('74');

    $program = PtrDocumentType::where('code', 'PROGRAM')->first();

    // Stage the PDF on its own, the way picking a file does.
    $staged = $this->actingAs($applicant)->post(
        route('applicant.post_training.stage_document'),
        [
            'document'             => UploadedFile::fake()->create('program.pdf', 90, 'application/pdf'),
            'ptr_document_type_id' => $program->id,
        ]
    )->assertOk()->json();

    expect($staged['ok'])->toBeTrue();
    expect($staged['token'])->toEndWith('.pdf');

    $this->actingAs($applicant)->postJson(
        route('applicant.post_training.draft.save', $ntc->id),
        ['payload' => ['documents' => [
            (string) $program->id => ['token' => $staged['token'], 'name' => 'program.pdf'],
        ]]]
    )->assertOk();

    // Submit WITHOUT re-picking that file — the draft already holds it.
    $photoToken = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    $fields = ptrSubmissionFields($ntc);
    unset($fields[$program->inputName()]);

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge($fields, ['participants' => ptrOneParticipant('CERT-74', $photoToken)])
    )->assertSessionHas('success');

    $report = PostTrainingReport::where('ntc_report_id', $ntc->id)->first();
    $doc    = $report->documents->firstWhere('ptr_document_type_id', $program->id);

    expect($doc->file_path)->not->toBeNull();
    expect($doc->original_filename)->toBe('program.pdf');

    // Submitting retires the draft and the staged copy with it.
    expect(PostTrainingDraft::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
    $stagingPath = ApplicantStoragePath::ptrStaging(
        Accreditation::where('user_id', $applicant->id)->latest()->first()?->accreditationType?->name,
        $applicant->id
    );
    expect(Storage::disk('local')->exists($stagingPath . '/' . $staged['token']))->toBeFalse();
});

test('a requirement with neither a picked nor a staged file is still refused', function () {
    [$applicant, $ntc] = ptrFixture('75');

    $program = PtrDocumentType::where('code', 'PROGRAM')->first();

    $photoToken = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    $fields = ptrSubmissionFields($ntc);
    unset($fields[$program->inputName()]);

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge($fields, ['participants' => ptrOneParticipant('CERT-75', $photoToken)])
    )->assertSessionHasErrors($program->inputName());

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
});

test('a draft can be thrown away on request', function () {
    [$applicant, $ntc] = ptrFixture('76');

    $this->actingAs($applicant)->postJson(
        route('applicant.post_training.draft.save', $ntc->id),
        ['payload' => ['applicant_remarks' => 'changed my mind']]
    )->assertOk();

    $this->actingAs($applicant)
        ->deleteJson(route('applicant.post_training.draft.discard', $ntc->id))
        ->assertOk();

    expect(PostTrainingDraft::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
});

test('two trainings keep entirely separate drafts', function () {
    [$applicant, $firstNtc] = ptrFixture('77');

    // A second acknowledged training for the same FATPro.
    $secondNtc = NtcReport::create([
        'accreditation_id'     => $firstNtc->accreditation_id,
        'ntc_training_type_id' => $firstNtc->ntc_training_type_id,
        'ntc_training_mode_id' => $firstNtc->ntc_training_mode_id,
        'training_start_date'  => now()->subDays(6)->format('Y-m-d'),
        'training_end_date'    => now()->subDays(5)->format('Y-m-d'),
        'status'               => 'acknowledged',
        'acknowledged_at'      => now()->subDays(15),
    ]);

    $prepost = PtrDocumentType::where('code', 'PREPOST')->first();

    // Only the first training has a staged document.
    $this->actingAs($applicant)->postJson(
        route('applicant.post_training.draft.save', $firstNtc->id),
        ['payload' => [
            'applicant_remarks' => 'belongs to the first',
            'documents'         => [(string) $prepost->id => ['token' => 'first.pdf', 'name' => 'first.pdf']],
        ]]
    )->assertOk();

    $this->actingAs($applicant)->postJson(
        route('applicant.post_training.draft.save', $secondNtc->id),
        ['payload' => ['applicant_remarks' => 'belongs to the second']]
    )->assertOk();

    $first  = PostTrainingDraft::where('ntc_report_id', $firstNtc->id)->first();
    $second = PostTrainingDraft::where('ntc_report_id', $secondNtc->id)->first();

    expect($first->payload['applicant_remarks'])->toBe('belongs to the first');
    expect($second->payload['applicant_remarks'])->toBe('belongs to the second');

    // The second training must not inherit the first's staged document — that
    // is what made a step look satisfied on a training nobody had touched.
    expect($first->payload['documents'])->toHaveKey((string) $prepost->id);
    expect($second->payload)->not->toHaveKey('documents');
    expect($second->stagedTokens())->toBe([]);

    // And each trigger carries only its own draft to the dialog.
    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'belongs to the first'))->toBe(1);
    expect(substr_count($html, 'belongs to the second'))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Participant field formats
|--------------------------------------------------------------------------
|
| Region and City are picked from the PSGC register rather than typed, and the
| two contact numbers follow the shapes the landing page's registration form
| already enforces. These guard the server side of all four: a dropdown can
| only constrain the browser, and the directory arrives as one JSON field a
| crafted post is free to write by hand.
|
*/

/** One encoded row, valid apart from whatever the caller overrides. */
function ptrRow(string $photoToken, array $overrides = []): string
{
    return json_encode([array_merge([
        'certificate_number' => 'CERT-FMT',
        'photo_token'        => $photoToken,
        'last_name'          => 'Santos',
        'first_name'         => 'Mina',
        'sex'                => 'Female',
        'age'                => 29,
        'company'            => 'Acme',
        'position'           => 'Safety Officer',
        'company_region'     => 'NCR',
        'company_city'       => 'Quezon City',
        'industry'           => 'Manufacturing',
        'mobile_no'          => '09171234567',
        'mode_of_training'   => 'Face to Face',
        ...ptrRequiredExtras(),
    ], $overrides)]);
}

test('a company region has to be one the PSGC register actually lists', function () {
    [$applicant, $ntc] = ptrFixture('80');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    // Region IV was split into CALABARZON and MIMAROPA in 2002. It is not a
    // region any more, and typing it by hand must not bring it back.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, [
            'company_region' => 'Region IV',
            'company_city'   => 'Calamba City',
        ])])
    )->assertSessionHasErrors('participants.0.company_region');

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
});

test('a city has to belong to the region it is filed under', function () {
    [$applicant, $ntc] = ptrFixture('81');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    // Both halves are real; together they are not. The dropdowns cannot offer
    // this pairing, but the directory posts as JSON and nothing stops a
    // hand-built request from sending it.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, [
            'company_region' => 'NCR',
            'company_city'   => 'Cebu City',
        ])])
    )->assertSessionHasErrors('participants.0.company_city');

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();

    // Filed under its own region it goes straight through.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, [
            'company_region' => 'Region VII',
            'company_city'   => 'Cebu City',
        ])])
    )->assertSessionHasNoErrors();

    expect(PtrParticipant::first()->company_region)->toBe('Region VII');
});

test('a mobile number is held to the registration form shape', function () {
    [$applicant, $ntc] = ptrFixture('82');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    foreach (['0917 123 4567', '9171234567', '091712345', '+19171234567', 'not a number'] as $bad) {
        $this->actingAs($applicant)->post(
            route('applicant.post_training.store', $ntc->id),
            array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, ['mobile_no' => $bad])])
        )->assertSessionHasErrors('participants.0.mobile_no');
    }

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();

    // The international form the registration form accepts is accepted here too.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, ['mobile_no' => '+639171234567'])])
    )->assertSessionHasNoErrors();

    expect(PtrParticipant::first()->mobile_no)->toBe('+639171234567');
});

test('a company landline is required and must be ten digits', function () {
    [$applicant, $ntc] = ptrFixture('83');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    foreach (['02-1234-5678', '8123456', '028123456789'] as $bad) {
        $this->actingAs($applicant)->post(
            route('applicant.post_training.store', $ntc->id),
            array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, ['company_landline' => $bad])])
        )->assertSessionHasErrors('participants.0.company_landline');
    }

    // Left out entirely it is refused too: every Directory column but Middle
    // Name and Suffix is required.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, ['company_landline' => ''])])
    )->assertSessionHasErrors('participants.0.company_landline');

    expect(PtrParticipant::count())->toBe(0);
});

test('both e-mail columns have to be real addresses', function () {
    [$applicant, $ntc] = ptrFixture('84');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    foreach (['company_email', 'personal_email'] as $field) {
        $this->actingAs($applicant)->post(
            route('applicant.post_training.store', $ntc->id),
            array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, [$field => 'acme.test'])])
        )->assertSessionHasErrors("participants.0.{$field}");
    }

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
});

test('the grid asks for the region before the city, and picks both from a list', function () {
    [$applicant, $ntc] = ptrFixture('85');

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    // Region leads, in the header and in the row template alike.
    expect(strpos($html, 'Company Address (Region)'))
        ->toBeLessThan(strpos($html, 'Company Address (City / Municipality)'));

    expect(strpos($html, 'data-field="company_region"'))
        ->toBeLessThan(strpos($html, 'data-field="company_city"'));

    // Both are dropdowns, and the city waits on a region before offering anything.
    expect($html)->toContain('<select class="ptr-dir-input" data-field="company_region" data-ph-region required>');
    expect($html)->toContain('<select class="ptr-dir-input" data-field="company_city" data-ph-city required disabled>');

    // The contact columns carry the registration form's shapes.
    expect($html)->toContain('data-ph-mobile');
    expect($html)->toContain('data-ph-landline');

    // The 1,634 municipalities are fetched, not inlined into every row template.
    expect($html)->not->toContain('Cebu City');
});

test('the PSGC register covers every region and keeps its cities apart', function () {
    $codes = PhLocations::regionCodes();

    expect($codes)->toHaveCount(17);
    expect($codes[0])->toBe('NCR');

    $total = 0;

    foreach ($codes as $code) {
        $cities = PhLocations::citiesIn($code);

        expect($cities)->not->toBeEmpty();
        $total += count($cities);
    }

    expect($total)->toBe(1634);

    expect(PhLocations::isCityIn('Cebu City', 'Region VII'))->toBeTrue();
    expect(PhLocations::isCityIn('Cebu City', 'NCR'))->toBeFalse();
    expect(PhLocations::isRegion('Region IV'))->toBeFalse();
    expect(PhLocations::isRegion('Region IV-A'))->toBeTrue();
});

/**
 * The Directory columns that became required alongside the ones every payload
 * above already names, plus requirement 5's two scores, which ride on each
 * participant row.
 */
function ptrRequiredExtras(): array
{
    return [
        'total_workers'    => 50,
        'company_email'    => 'safety@acme.example',
        'personal_email'   => 'participant@mail.example',
        'company_landline' => '0281234567',
        'batch_no'         => '2026-01',
        'pre_test_score'   => '12/20',
        'post_test_score'  => '18/20',
        'catch_up'         => 'No',
    ];
}

/*
|--------------------------------------------------------------------------
| Requirement 5 — Pre- and Post-Test Summary Evaluation
|--------------------------------------------------------------------------
|
| Encoded against the Directory rather than uploaded: every participant
| carries a pre-test and a post-test score, typed as free text.
|
*/

/** A Training Evaluator, for the review page and the verdicts. */
function ptrScoresEvaluator(string $suffix): User
{
    $evaluator = User::forceCreate([
        'email'        => "ptr_scores_eval_{$suffix}@example.com",
        'password'     => bcrypt('password'),
        'role_id'      => Role::firstOrCreate(['name' => 'Admin'])->id,
        'profile_type' => 'Individual',
    ]);

    AdminProfile::create([
        'user_id'       => $evaluator->id,
        'division_id'   => Division::firstOrCreate(['name' => 'HCD'])->id,
        'first_name'    => 'Score',
        'last_name'     => 'Evaluator',
        'position'      => 'LSO III',
        'admin_role_id' => AdminRole::firstOrCreate(['name' => 'Training Evaluator'])->id,
    ]);

    return $evaluator;
}

test('the Pre- and Post-Test Summary Evaluation is encoded, not uploaded', function () {
    $prepost = PtrDocumentType::where('code', 'PREPOST')->first();

    expect($prepost->entry_type)->toBe('scores');
    expect($prepost->isScores())->toBeTrue();
    expect($prepost->isFile())->toBeFalse();
    expect($prepost->stepKind())->toBe('scores');
    expect($prepost->accepted_extensions)->toBeNull();
});

test('every participant needs both a pre-test and a post-test score', function () {
    [$applicant, $ntc] = ptrFixture('90');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    foreach (['pre_test_score', 'post_test_score'] as $field) {
        $this->actingAs($applicant)->post(
            route('applicant.post_training.store', $ntc->id),
            array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, [$field => ''])])
        )->assertSessionHasErrors("participants.0.{$field}");
    }

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
});

test('only Middle Name and Suffix may be left blank in the Directory', function () {
    [$applicant, $ntc] = ptrFixture('91');

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(200),
        ])->json('token');

    foreach (['total_workers', 'company_email', 'personal_email', 'company_landline', 'batch_no'] as $field) {
        $this->actingAs($applicant)->post(
            route('applicant.post_training.store', $ntc->id),
            array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, [$field => ''])])
        )->assertSessionHasErrors("participants.0.{$field}");
    }

    // Middle Name and Suffix blank is fine.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, ['middle_name' => '', 'suffix' => ''])])
    )->assertSessionHasNoErrors();
});

test('the wizard marks required Directory columns and renders the scores step', function () {
    [$applicant] = ptrFixture('92');

    $html = $this->actingAs($applicant)->get(route('applicant.ntc.index'))->assertOk()->getContent();

    // A * on every required column header: 18 in the Directory, plus the two
    // score columns on step 5.
    expect(substr_count($html, 'class="ptr-dir-req"'))->toBeGreaterThanOrEqual(20);

    // Step 5 is the scores table, not a dropzone.
    expect($html)->toContain('data-kind="scores"');
    expect($html)->toContain('id="ptrScoresTable"');
    expect($html)->not->toContain('name="file_prepost"');
});

test('the evaluator reads the scores and judges them with one Accept or Decline', function () {
    [, , , $report] = ptrFiledReport('93');
    $evaluator = ptrScoresEvaluator('93');

    $report->load('documents.documentType');
    $scoresId = $report->scoresDocument()->id;

    $html = $this->actingAs($evaluator)
        ->get(route('admin.hcd.reports.post_training.show', $report->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Ana Reyes');
    expect($html)->toContain('12/20');
    expect($html)->toContain('18/20');
    expect($html)->toContain("setNtcDocStatus({$scoresId}, 'approved')");
    expect($html)->toContain('name="evaluations[' . $scoresId . '][remarks]"');
});

test('declined scores are re-entered through the corrections dialog', function () {
    [$applicant, , , $report] = ptrFiledReport('94');
    $evaluator = ptrScoresEvaluator('94');

    $report->load('documents.documentType');
    $scoresId = $report->scoresDocument()->id;

    // Accept everything but the scores.
    $this->actingAs($evaluator)->postJson(
        route('admin.hcd.reports.post_training.finalize_evaluation', $report->id),
        ['evaluations' => $report->documents->map(fn ($d) => $d->id === $scoresId
            ? ['id' => $d->id, 'status' => 'rejected', 'remarks' => 'Post-test totals do not match the sheets.']
            : ['id' => $d->id, 'status' => 'approved', 'remarks' => '']
        )->values()->all()]
    )->assertOk()->assertJson(['has_rejections' => true]);

    $report->refresh()->load(['documents.documentType', 'participants']);

    expect($report->hasScoreCorrections())->toBeTrue();
    expect($report->declinedSectionCount())->toBe(1);

    // The dialog offers the score table, pre-filled.
    $html = $this->actingAs($applicant)->get(route('applicant.ntc.index'))->assertOk()->getContent();
    expect($html)->toContain('data-correction-scores');
    expect($html)->toContain('Post-test totals do not match the sheets.');

    $participant = $report->participants->first();

    // A blank score is refused outright.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.corrections', $report->id),
        ['test_scores' => json_encode([$participant->id => ['pre_test_score' => '12/20', 'post_test_score' => '']])]
    )->assertSessionHasErrors('test_scores');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.corrections', $report->id),
        ['test_scores' => json_encode([$participant->id => ['pre_test_score' => '12/20', 'post_test_score' => '19/20']])]
    )->assertSessionHas('success');

    $participant->refresh();
    expect($participant->post_test_score)->toBe('19/20');

    $report->refresh()->load('documents.documentType');
    expect($report->scoresDocument()->status)->toBe('returned');
    expect($report->status)->toBe('submitted');
});

/*
|--------------------------------------------------------------------------
| Catch-up participants
|--------------------------------------------------------------------------
|
| A participant who began this course in one of the FATPro's earlier
| trainings and completes it in this one. The start is that earlier
| training's first day; the end is this training's last.
|
*/

/** Another of the same FATPro's trainings, for catch-up sources. */
function ptrOtherTraining(NtcReport $like, array $overrides = []): NtcReport
{
    return NtcReport::create(array_merge([
        'accreditation_id'     => $like->accreditation_id,
        'ntc_training_type_id' => $like->ntc_training_type_id,
        'ntc_training_mode_id' => $like->ntc_training_mode_id,
        'venue'                => 'Earlier Venue',
        'training_start_date'  => now()->subDays(40)->format('Y-m-d'),
        'training_end_date'    => now()->subDays(38)->format('Y-m-d'),
        'status'               => 'acknowledged',
        'acknowledged_at'      => now()->subDays(50),
    ], $overrides))->refresh();
}

test('catch-up choices are the FATPro\'s other trainings already held, of any type', function () {
    [$applicant, $ntc] = ptrFixture('c1');

    // Offered: already held — whatever the type, and whether before or after.
    $earlier   = ptrOtherTraining($ntc);
    $otherType = ptrOtherTraining($ntc, [
        'ntc_training_type_id' => NtcTrainingType::where('id', '!=', $ntc->ntc_training_type_id)->value('id'),
        'training_start_date'  => now()->subDays(30)->format('Y-m-d'),
        'training_end_date'    => now()->subDays(29)->format('Y-m-d'),
    ]);
    $heldAfter = ptrOtherTraining($ntc, [
        'training_start_date' => now()->subDays(5)->format('Y-m-d'),
        'training_end_date'   => now()->subDays(4)->format('Y-m-d'),
    ]);

    // Not offered: cancelled, not yet held, or another FATPro's.
    ptrOtherTraining($ntc, ['status' => 'cancelled']);
    ptrOtherTraining($ntc, [
        'training_start_date' => now()->addDays(20)->format('Y-m-d'),
        'training_end_date'   => now()->addDays(21)->format('Y-m-d'),
    ]);
    [, $someoneElses] = ptrFixture('c2');
    $someoneElses->update(['training_start_date' => now()->subDays(60), 'training_end_date' => now()->subDays(59)]);

    // Most recent first; the training being reported is never offered.
    expect(collect($ntc->catchUpOptions())->pluck('id')->all())
        ->toBe([$heldAfter->id, $otherType->id, $earlier->id]);

    // The label names the training type, since the list mixes courses.
    expect($ntc->catchUpOptions()[1]['label'])->toContain('(' . $otherType->trainingType->code . ')');

    // And the Submit button hands them to the dialog.
    $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->assertSee('data-catchup-options', false)
        ->assertSee($earlier->reference_number);
});

test('a catch-up participant records where they started and when they finished', function () {
    [$applicant, $ntc] = ptrFixture('c3');
    $earlier = ptrOtherTraining($ntc);

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(150),
        ])->json('token');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, [
            'catch_up'               => 'Yes',
            'catch_up_ntc_report_id' => $earlier->id,
            // Whatever the post claims, the server works the dates out itself.
            'catch_up_start_date'    => '2000-01-01',
        ])])
    )->assertSessionHas('success');

    $participant = PtrParticipant::firstOrFail();

    expect($participant->is_catch_up)->toBeTrue();
    expect($participant->catch_up_ntc_report_id)->toBe($earlier->id);
    expect($participant->catch_up_start_date->toDateString())->toBe($earlier->training_start_date->toDateString());
    expect($participant->catch_up_end_date->toDateString())->toBe($ntc->training_end_date->toDateString());
});

test('a participant who is not a catch-up carries no catch-up dates', function () {
    [$applicant, $ntc] = ptrFixture('c4');
    $earlier = ptrOtherTraining($ntc);

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(150),
        ])->json('token');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, [
            'catch_up'               => 'No',
            'catch_up_ntc_report_id' => $earlier->id,
        ])])
    )->assertSessionHas('success');

    $participant = PtrParticipant::firstOrFail();

    expect($participant->is_catch_up)->toBeFalse();
    expect($participant->catch_up_ntc_report_id)->toBeNull();
    expect($participant->catch_up_start_date)->toBeNull();
});

test('a catch-up participant must name one of the FATPro\'s own trainings already held', function () {
    [$applicant, $ntc] = ptrFixture('c5');
    [, $someoneElses] = ptrFixture('c6');
    $notYetHeld = ptrOtherTraining($ntc, [
        'training_start_date' => now()->addDays(20)->format('Y-m-d'),
        'training_end_date'   => now()->addDays(21)->format('Y-m-d'),
    ]);

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(150),
        ])->json('token');

    foreach ([null, $someoneElses->id, $notYetHeld->id, $ntc->id] as $sourceId) {
        $this->actingAs($applicant)->post(
            route('applicant.post_training.store', $ntc->id),
            array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, [
                'catch_up'               => 'Yes',
                'catch_up_ntc_report_id' => $sourceId,
            ])])
        )->assertSessionHasErrors('participants.0.catch_up_ntc_report_id');
    }

    // Catch Up itself must be answered.
    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, ['catch_up' => ''])])
    )->assertSessionHasErrors('participants.0.catch_up');

    expect(PostTrainingReport::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
});

test('the wizard offers Catch Up, and the evaluator sees who is one', function () {
    [$applicant, $ntc] = ptrFixture('c7');
    $earlier = ptrOtherTraining($ntc);

    $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertSee('data-field="catch_up"', false)
        ->assertSee('data-field="catch_up_ntc_report_id"', false);

    $token = $this->actingAs($applicant)
        ->post(route('applicant.post_training.participant_photo'), [
            'photo' => UploadedFile::fake()->image('id.jpg')->size(150),
        ])->json('token');

    $this->actingAs($applicant)->post(
        route('applicant.post_training.store', $ntc->id),
        array_merge(ptrSubmissionFields($ntc), ['participants' => ptrRow($token, [
            'catch_up' => 'Yes', 'catch_up_ntc_report_id' => $earlier->id,
        ])])
    )->assertSessionHas('success');

    $report = PostTrainingReport::where('ntc_report_id', $ntc->id)->firstOrFail();

    $this->actingAs(ptrScoresEvaluator('c7'))
        ->get(route('admin.hcd.reports.post_training.show', $report->id))
        ->assertOk()
        ->assertSee('Catch Up')
        ->assertSee($earlier->training_start_date->format('M d, Y'))
        ->assertSee($earlier->reference_number);
});
