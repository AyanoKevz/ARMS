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
use App\Models\PostTrainingReport;
use App\Models\PtrDocumentType;
use App\Models\PtrParticipant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

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

    // Four plain uploads are left: the roster stopped being one of them.
    expect(PtrDocumentType::where('entry_type', 'file')->count())->toBe(4);
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
        'company_landline'   => '02-1234-5678',
        'mode_of_training'   => 'Face to Face',
        'batch_no'           => '2026-01',
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

test('one declined participant declines the whole Directory', function () {
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

    $rows = $report->participants;

    // Accept every attachment, accept one participant, decline the other.
    $evaluations = $report->documents->map(fn ($d) => [
        'id' => $d->id, 'status' => 'approved', 'remarks' => '',
    ])->values()->all();

    $this->actingAs($evaluator)->postJson(
        route('admin.hcd.reports.post_training.finalize_evaluation', $report->id),
        [
            'evaluations'  => $evaluations,
            'participants' => [
                ['id' => $rows[0]->id, 'status' => 'approved'],
                ['id' => $rows[1]->id, 'status' => 'rejected'],
            ],
            'directory_remarks' => 'Row 2 has the wrong certificate number.',
        ]
    )->assertOk();

    $report->refresh()->load(['documents.documentType', 'participants']);

    // The section takes the verdict its rows imply, with the one shared remark.
    $directory = $report->directoryDocument();
    expect($directory->status)->toBe('rejected');
    expect($directory->remarks)->toBe('Row 2 has the wrong certificate number.');

    expect($report->hasDirectoryCorrections())->toBeTrue();
    expect($report->rejectedParticipants())->toHaveCount(1);

    // The declined row keeps its ID picture: the FATPro edits it in place.
    expect($report->participants->firstWhere('status', 'rejected')->hasIdPicture())->toBeTrue();

    // And the whole report is declined, not accepted.
    expect($report->status)->toBe('declined');
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
            'company_city'       => 'X',
            'company_region'     => 'X',
            'industry'           => 'X',
            'mobile_no'          => '09000000000',
            'mode_of_training'   => 'Face to Face',
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
            'company_city'       => 'X',
            'company_region'     => 'X',
            'industry'           => 'X',
            'mobile_no'          => '09000000000',
            'mode_of_training'   => 'Face to Face',
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

test('the evaluator page renders the participant table with per-row verdicts', function () {
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

    $row = $report->participants->first();

    expect($html)->toContain('Bautista');
    expect($html)->toContain('VIEW-1');
    expect($html)->toContain('ptr-participant-status-' . $row->id);
    expect($html)->toContain('name="directory_remarks"');

    // The section is judged through its rows, so it is flagged for the script
    // that derives the Directory's verdict.
    expect($html)->toContain('data-ptr-directory="1"');
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

    // Still four PDF uploads; the video did not become a fifth.
    expect(PtrDocumentType::where('entry_type', 'file')->count())->toBe(4);
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
