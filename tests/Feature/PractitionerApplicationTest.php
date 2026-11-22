<?php

use App\Mail\AdminApplicationSubmittedEmail;
use App\Mail\AdminDocumentsUploadedEmail;
use App\Mail\ApplicationEvaluationStartedEmail;
use App\Mail\DocumentRejectionEmail;
use App\Mail\AdminInterviewSlotBookedEmail;
use App\Mail\EvaluatorAssignedEmail;
use App\Mail\InterviewScheduleEmail;
use App\Mail\InterviewSlotSelectionEmail;
use App\Mail\PractitionerApplicationArchivedEmail;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\InterviewSlot;
use Carbon\Carbon;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Services\CacheService;
use App\Services\PctService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Storage::fake('local');
    Mail::fake();

    // Seeded by AdminUserSeeder.
    $this->accTeamLead = User::where('email', 'oshc1987accreditation@gmail.com')->firstOrFail();
    $this->accVerifier = User::where('email', 'kevin.goto@oshc.dole.gov.ph')->firstOrFail();
    $this->hcdTeamLead = User::where('email', 'hcdarms2026@gmail.com')->firstOrFail();
});

/**
 * Act as an admin on a fresh session: AuthenticateAdminSession signs out a
 * session that changes hands between two admin accounts.
 */
function pracAs($test, User $user)
{
    $test->flushSession();

    return $test->actingAs($user);
}

/** Whether a mailable went to an address, sent directly or queued. */
function pracMailed(string $mailable, string $to): bool
{
    $match = fn ($m) => $m->hasTo($to);

    return Mail::sent($mailable, $match)->isNotEmpty() || Mail::queued($mailable, $match)->isNotEmpty();
}

/** A complete, valid OSH Practitioner registration. */
function pracPayload(array $override = []): array
{
    $pdf = fn (string $name) => UploadedFile::fake()->create($name, 50, 'application/pdf');

    return array_replace_recursive([
        'accreditation_type_id' => config('accreditation.types.practitioner'),
        'profile_type'          => 'Individual',
        'email'                 => 'practitioner@example.com',
        'password'              => 'Secret1234',
        'password_confirmation' => 'Secret1234',
        'first_name'            => 'Juan',
        'middle_name'           => '',
        'last_name'             => 'Dela Cruz',
        'sex'                   => 'Male',
        'birthday'              => '1990-01-15',
        'region'                => 'NCR',
        'city'                  => 'Quezon City',
        'address'               => '12 Mabini St.',
        'practitioner' => [
            'profile' => [
                'civil_status'     => 'Single',
                'citizenship'      => 'Filipino',
                'religion'         => '',
                'contact_number'   => '09171234567',
                'type_of_industry' => 'Construction',
                'photo'            => UploadedFile::fake()->image('photo.jpg', 200, 200),
            ],
            'workplace'    => ['hazard_level' => 'Hazardous', 'employees_male' => '10', 'employees_female' => '5', 'region_of_employment' => 'NCR — National Capital Region'],
            'experience'   => ['total_osh_experience_years' => '4'],
            'declarations' => [
                'has_pending_admin_case' => '0', 'has_pending_criminal_case' => '0', 'has_crime_conviction' => '0',
                'has_admin_offense_conviction' => '0', 'has_forced_separation' => '0',
            ],
            'education'  => [['degree' => 'BS Civil Engineering', 'school_name_address' => 'UP Diliman']],
            'licenses'   => [['license_type' => '', 'prc_license_no' => '']],
            'work'       => [['company' => 'ABC Builders', 'designation' => 'Safety Officer', 'date_from' => '2020-01-01']],
            'trainings'  => [['title' => 'BOSH', 'conducted_by' => 'OSHC', 'no_of_hours' => '40']],
            'lectures'   => [['topic' => 'N.A.', 'conducted_for' => 'N.A.']],
            'skills'     => [['trade_occupation' => 'Safety Officer', 'field_of_expertise' => 'Construction']],
            'references' => [
                ['full_name' => 'Ref One', 'designation' => 'HR', 'company_address' => 'Pasig', 'contact_number' => '09170000001'],
                ['full_name' => 'Ref Two', 'designation' => 'Engineer', 'company_address' => 'Makati', 'contact_number' => '09170000002'],
                ['full_name' => 'Ref Three', 'designation' => 'Professor', 'company_address' => 'QC', 'contact_number' => '09170000003'],
            ],
        ],
        'documents' => [
            'PRAC_01' => $pdf('coe.pdf'),
            'PRAC_02' => $pdf('duties.pdf'),
            'PRAC_04' => $pdf('bosh.pdf'),
            'PRAC_05' => $pdf('attendance.pdf'),
            'PRAC_06' => $pdf('tor.pdf'),
            'PRAC_07' => $pdf('proof.pdf'),
        ],
    ], $override);
}

/**
 * A corrected application form as the tracking page sends it: the Step 3
 * person fields under person[...] and Steps 3–13 under practitioner[...],
 * without the photo (one is on file) or any document uploads.
 */
function pracCorrection(array $override = []): array
{
    $payload = pracPayload();

    return array_replace_recursive([
        'person'       => \Illuminate\Support\Arr::only($payload, ['first_name', 'middle_name', 'last_name', 'sex', 'birthday', 'region', 'city', 'address']),
        'practitioner' => \Illuminate\Support\Arr::except($payload['practitioner'], ['profile.photo']),
    ], $override);
}

/** Register and verify; returns the created application. */
function pracSubmit($test, array $override = []): Application
{
    $test->post(route('register.store'), pracPayload($override))->assertOk()->assertJson(['status' => 'pending']);

    $pending = PendingRegistration::where('email', $override['email'] ?? 'practitioner@example.com')->firstOrFail();
    $test->get(route('register.verify', $pending->token))->assertOk();

    return Application::where('tracking_number', '!=', '')->latest('id')->firstOrFail();
}

function pracSetStatus(Application $application, string $status): void
{
    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id'      => ApplicationStatus::findByName($status)->id,
        'remarks'        => 'test',
    ]);
    $application->unsetRelation('latestStatus');
}

/** Start the evaluation and approve every document: the applicant is for interview. */
function pracApproveAll($test, Application $application): void
{
    pracAs($test, $test->accVerifier)->post(route('admin.accreditation.practitioner.start', $application));

    pracAs($test, $test->accVerifier)
        ->postJson(route('admin.accreditation.practitioner.finalize_evaluation', $application), [
            'evaluations' => $application->documents()->get()->map(fn ($doc) => ['id' => $doc->id, 'status' => 'approved'])->all(),
        ])
        ->assertJson(['action' => 'proceed_to_interview']);

    CacheService::bustTrackingCache($application->tracking_number);
}

function pracSlot(string $date, string $start, string $end, array $attributes = []): InterviewSlot
{
    return InterviewSlot::create($attributes + [
        'slot_date'  => $date,
        'start_time' => $start . ':00',
        'end_time'   => $end . ':00',
        'mode'       => 'f2f',
        'venue'      => 'Occupational Safety and Health Center',
    ]);
}

afterEach(function () {
    Carbon::setTestNow();
});

test('a practitioner submission is stored, gets its AF-PCN-A1 form and notifies the Accreditation admins', function () {
    $application = pracSubmit($this);

    expect($application->isPractitioner())->toBeTrue()
        ->and($application->latestStatus->status->name)->toBe('Submitted');

    $profile = $application->user->individualProfile;
    expect($profile->civil_status)->toBe('Single')
        ->and($profile->workplaceProfile->total_workforce_size)->toBe(15);

    $prac = $application->practitionerProfile;
    expect($prac->total_osh_experience_years)->toBe(4)
        ->and($prac->has_forced_separation)->toBeFalse()
        ->and($application->practitionerReferences)->toHaveCount(3)
        // The blank license row is dropped, not stored.
        ->and($application->practitionerLicenses)->toHaveCount(0);

    Storage::disk('local')->assertExists($prac->photo_path);
    Storage::disk('local')->assertExists($prac->application_form_path);
    expect($application->documents)->toHaveCount(7);
    // The generated form is one of them (PRAC_00), pointing at the stored PDF.
    expect($application->documents->firstWhere('documentField.code', 'PRAC_00')?->userDocument?->file_path)->toBe($prac->application_form_path);

    // PCT: no clock while pending — it starts with the evaluation, as FATPro's does.
    expect($application->pctEntries()->count())->toBe(0);

    expect(pracMailed(AdminApplicationSubmittedEmail::class, 'oshc1987accreditation@gmail.com'))->toBeTrue();
    expect(pracMailed(AdminApplicationSubmittedEmail::class, 'kevin.goto@oshc.dole.gov.ph'))->toBeTrue();
    expect(pracMailed(AdminApplicationSubmittedEmail::class, 'hcdarms2026@gmail.com'))->toBeFalse();
});

test('the server rejects a practitioner submission with fewer than three character references', function () {
    $payload = pracPayload();
    $payload['practitioner']['references'] = array_slice($payload['practitioner']['references'], 0, 2);

    $this->postJson(route('register.store'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('practitioner.references');
});

test('a Yes declaration needs its details, which are stored, printed and given back for correction; a No drops them', function () {
    // Yes without details: refused, and only for the questions answered Yes.
    $this->postJson(route('register.store'), pracPayload([
        'practitioner' => ['declarations' => ['has_pending_criminal_case' => '1', 'has_forced_separation' => '1']],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['practitioner.declarations.pending_case_details', 'practitioner.declarations.forced_separation_reasons'])
        ->assertJsonMissingValidationErrors(['practitioner.declarations.crime_conviction_details', 'practitioner.declarations.admin_offense_details']);

    $details     = 'Estafa, RTC Branch 12 Quezon City, Crim. Case No. 123, pending';
    $application = pracSubmit($this, [
        'practitioner' => ['declarations' => [
            'has_pending_criminal_case' => '1',
            'pending_case_details'      => $details,
            // Typed, then the answer went back to No.
            'crime_conviction_details'  => 'Should not be kept',
        ]],
    ]);

    $prac = $application->practitionerProfile;
    expect($prac->has_pending_criminal_case)->toBeTrue()
        ->and($prac->pending_case_details)->toBe($details)
        ->and($prac->crime_conviction_details)->toBeNull();

    expect(\App\Support\PractitionerForm::fromApplication($application->fresh())['declaration_details'])->toBe([
        'pending_case_details'      => $details,
        'crime_conviction_details'  => 'N/A',
        'admin_offense_details'     => 'N/A',
        'forced_separation_reasons' => 'N/A',
    ]);

    // Sent back for correction: the details box under a Yes comes back open and filled in.
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.start', $application));
    pracAs($this, $this->accVerifier)
        ->postJson(route('admin.accreditation.practitioner.finalize_evaluation', $application), [
            'evaluations' => $application->documents()->with('documentField')->get()
                ->map(fn ($doc) => $doc->documentField->code === 'PRAC_00'
                    ? ['id' => $doc->id, 'status' => 'rejected', 'remarks' => 'Incomplete details']
                    : ['id' => $doc->id, 'status' => 'approved'])->values()->all(),
        ])
        ->assertJson(['action' => 'rejection_sent']);
    CacheService::bustTrackingCache($application->tracking_number);

    $html = $this->get(route('track', ['tracking_number' => $application->tracking_number]))->assertOk()->getContent();
    expect($html)->toContain($details . '</textarea>')
        ->toMatch('#data-prac-decl-details="has_pending_admin_case has_pending_criminal_case"\s*>#')
        ->toMatch('#data-prac-decl-details="has_crime_conviction"\s*hidden#');

    // Corrected: the pending case is answered No now, an administrative offense Yes.
    $this->post(route('track.resubmit.all'), ['tracking_number' => $application->tracking_number] + pracCorrection([
        'practitioner' => ['declarations' => [
            'has_admin_offense_conviction' => '1',
            'admin_offense_details'        => 'Suspended for 30 days, 2021',
            'pending_case_details'         => $details,
        ]],
    ]))->assertSessionHas('success');

    expect($prac->fresh()->only(['has_pending_criminal_case', 'pending_case_details', 'has_admin_offense_conviction', 'admin_offense_details']))->toBe([
        'has_pending_criminal_case'    => false,
        'pending_case_details'         => null,
        'has_admin_offense_conviction' => true,
        'admin_offense_details'        => 'Suspended for 30 days, 2021',
    ]);
});

test('Review Details renders the AF-PCN-A1 form with N/A for blank fields and blank padding rows', function () {
    $payload = pracPayload();
    unset($payload['documents'], $payload['practitioner']['profile']['photo']);

    $html = $this->postJson(route('register.practitioner.preview'), $payload)->assertOk()->json('html');

    expect($html)->toContain('AF-PCN-A1')
        ->toContain('Page 1: Profile &amp; Education')
        ->toContain('Full Application Form')
        ->toContain('Dela Cruz')
        ->toContain('N/A')
        // Laid out as the printed form: the photo instructions, the years box, the details lines.
        ->toContain('SC: blue background')
        ->toContain('Years of OSH')
        ->toContain('If yes, give reasons');

    // Religion was left blank → N/A; no table cell is ever filled with N/A.
    expect(preg_match('#<td class="af-value[^"]*">N/A</td>#', $html))->toBe(0);
});

test('any Accreditation admin can start the evaluation, which notifies the applicant and completes PCT Step 2', function () {
    $application = pracSubmit($this);

    // No assigning: whoever starts it is in charge.
    pracAs($this, $this->accVerifier)
        ->post(route('admin.accreditation.practitioner.start', $application))
        ->assertRedirect(route('admin.accreditation.practitioner.show', $application));

    $application->refresh();
    expect($application->assigned_evaluator_id)->toBe($this->accVerifier->id)
        ->and($application->latestStatus->status->name)->toBe('Under Evaluation');

    // Steps 1 and 2 are recorded as done with no time counted; Step 3 starts now.
    $steps = $application->pctEntries()->get()->keyBy('step_number');
    expect($steps[1]->step_name)->toBe(PctService::PRACTITIONER_STEPS[1]['name'])
        ->and($steps[1]->completed_at)->not->toBeNull()
        ->and($steps[2]->completed_at)->not->toBeNull()
        ->and($steps[1]->totalElapsedSeconds() + $steps[2]->totalElapsedSeconds())->toBe(0)
        ->and($steps[3]->is_active)->toBeTrue()
        ->and($steps[3]->target_days)->toBe(5);

    expect(pracMailed(ApplicationEvaluationStartedEmail::class, 'practitioner@example.com'))->toBeTrue();
    expect(pracMailed(EvaluatorAssignedEmail::class, 'kevin.goto@oshc.dole.gov.ph'))->toBeFalse();

    // Starting twice is refused and changes nothing.
    pracAs($this, $this->accTeamLead)
        ->post(route('admin.accreditation.practitioner.start', $application))
        ->assertSessionHas('error');
    expect($application->fresh()->assigned_evaluator_id)->toBe($this->accVerifier->id);

    // HCD admins are outside the division.
    pracAs($this, $this->hcdTeamLead)
        ->post(route('admin.accreditation.practitioner.start', $application))
        ->assertForbidden();
});

test('the AF-PCN-A1 form is evaluated as a document; a rejection emails the applicant, pauses PCT, and correcting the form on the tracking page resumes it', function () {
    $application = pracSubmit($this);
    pracAs($this, $this->accTeamLead)->post(route('admin.accreditation.practitioner.start', $application));

    $documents = $application->documents()->with('documentField')->get();
    $form      = $documents->first(fn ($doc) => $doc->documentField->code === 'PRAC_00');
    expect($form)->not->toBeNull();

    $verdict = fn ($doc) => $doc->is($form)
        ? ['status' => 'rejected', 'remarks' => 'Unsigned form']
        : ['status' => 'approved', 'remarks' => null];

    // Auto-saved per click, as on the FATPro page; any admin in the division may evaluate.
    foreach ($documents as $doc) {
        pracAs($this, $this->accVerifier)
            ->postJson(route('admin.accreditation.practitioner.evaluate_item', $application), ['item_type' => 'document', 'item_id' => $doc->id] + $verdict($doc))
            ->assertOk()->assertJson(['success' => true]);
    }
    expect($form->fresh()->remarks)->toBe('Unsigned form');

    pracAs($this, $this->accVerifier)
        ->postJson(route('admin.accreditation.practitioner.finalize_evaluation', $application), [
            'evaluations' => $documents->map(fn ($doc) => ['id' => $doc->id] + $verdict($doc))->values()->all(),
        ])
        ->assertOk()
        ->assertJson(['success' => true, 'action' => 'rejection_sent', 'new_status' => 'For Update']);

    $application->refresh();
    expect($application->latestStatus->status->name)->toBe('For Update');
    expect($application->pctEntries()->where('step_number', 3)->first()->paused_at)->not->toBeNull();

    $listsForm = fn ($m) => $m->hasTo('practitioner@example.com') && $m->rejectedDocuments->pluck('id')->all() === [$form->id];
    expect(Mail::sent(DocumentRejectionEmail::class, $listsForm)->isNotEmpty() || Mail::queued(DocumentRejectionEmail::class, $listsForm)->isNotEmpty())->toBeTrue();

    // While For Update nothing can be evaluated.
    pracAs($this, $this->accVerifier)
        ->postJson(route('admin.accreditation.practitioner.evaluate_item', $application), ['item_type' => 'document', 'item_id' => $form->id, 'status' => 'approved'])
        ->assertStatus(422);

    // The tracking page gives the form back — Steps 3 to 13, filled in with
    // what was submitted — instead of an upload box for it.
    $this->get(route('track', ['tracking_number' => $application->tracking_number]))
        ->assertOk()
        ->assertSee('Unsigned form')
        ->assertSee('Step 3 &mdash; Personal Information', false)
        ->assertSee('Step 13 &mdash; Declarations', false)
        ->assertDontSee('Step 14 &mdash;', false)
        ->assertSee('name="person[last_name]"', false)
        ->assertSee('value="Dela Cruz"', false)
        ->assertSee('value="Single" selected', false)
        ->assertSee('value="Ref Three"', false)
        ->assertDontSee('name="files[' . $form->id . ']"', false);

    // The applicant corrects it there and resubmits; the form is generated again.
    $formPath = $application->practitionerProfile->application_form_path;
    $before   = Storage::disk('local')->get($formPath);

    $this->post(route('track.resubmit.all'), ['tracking_number' => $application->tracking_number] + pracCorrection([
        'person'       => ['last_name' => 'Dela Paz'],
        'practitioner' => [
            'profile'    => ['civil_status' => 'Married'],
            'references' => [3 => ['full_name' => 'Ref Four', 'designation' => 'Manager', 'company_address' => 'Taguig', 'contact_number' => '09170000004']],
        ],
    ]))->assertSessionHas('success');

    $application->refresh();
    expect($application->latestStatus->status->name)->toBe('Under Evaluation');
    expect($application->pctEntries()->where('step_number', 3)->first()->paused_at)->toBeNull();
    expect($form->fresh()->status)->toBe('pending')
        ->and($form->fresh()->remarks)->toBeNull();

    $profile = $application->user->individualProfile;
    expect($profile->last_name)->toBe('Dela Paz')
        ->and($profile->civil_status)->toBe('Married')
        ->and($application->practitionerReferences()->pluck('full_name')->all())->toBe(['Ref One', 'Ref Two', 'Ref Three', 'Ref Four'])
        ->and($application->practitionerEducations)->toHaveCount(1);

    // "View" on the form row opens the regenerated form, filled in with the corrections.
    expect($form->fresh()->userDocument->file_path)->toBe($application->practitionerProfile->application_form_path);
    expect(Storage::disk('local')->get($formPath))->not->toBe($before);
    expect(\App\Support\PractitionerForm::fromApplication($application->fresh())['signature'])->toBe('Juan Dela Paz');
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.application_form', $application))->assertOk();

    // Only the in-charge (the admin who started it) hears about the re-upload.
    expect(pracMailed(AdminDocumentsUploadedEmail::class, 'oshc1987accreditation@gmail.com'))->toBeTrue();
    expect(pracMailed(AdminDocumentsUploadedEmail::class, 'kevin.goto@oshc.dole.gov.ph'))->toBeFalse();
});

test('a corrected application form that fails validation saves nothing and comes back as typed; a new photo replaces the old', function () {
    $application = pracSubmit($this);
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.start', $application));

    $documents = $application->documents()->with('documentField')->get();
    pracAs($this, $this->accVerifier)
        ->postJson(route('admin.accreditation.practitioner.finalize_evaluation', $application), [
            'evaluations' => $documents->map(fn ($doc) => $doc->documentField->code === 'PRAC_00'
                ? ['id' => $doc->id, 'status' => 'rejected', 'remarks' => 'Wrong name']
                : ['id' => $doc->id, 'status' => 'approved'])->values()->all(),
        ])
        ->assertJson(['action' => 'rejection_sent']);
    CacheService::bustTrackingCache($application->tracking_number);

    $form     = $documents->first(fn ($doc) => $doc->documentField->code === 'PRAC_00');
    $oldPhoto = $application->practitionerProfile->photo_path;

    // Two references where three are required: refused, nothing saved…
    $short = pracCorrection(['person' => ['last_name' => 'Typed Name']]);
    $short['practitioner']['references'] = array_slice($short['practitioner']['references'], 0, 2);

    $this->post(route('track.resubmit.all'), ['tracking_number' => $application->tracking_number] + $short)
        ->assertSessionHasErrors('practitioner.references');

    expect($application->user->individualProfile->fresh()->last_name)->toBe('Dela Cruz')
        ->and($form->fresh()->status)->toBe('rejected')
        ->and($application->fresh()->latestStatus->status->name)->toBe('For Update');

    // …and the form comes back with what the applicant typed.
    $this->get(route('track', ['tracking_number' => $application->tracking_number]))
        ->assertOk()->assertSee('value="Typed Name"', false);

    // Sent again with a new photo: saved, and the photo on file replaced.
    $this->post(route('track.resubmit.all'), ['tracking_number' => $application->tracking_number] + pracCorrection([
        'practitioner' => ['profile' => ['photo' => UploadedFile::fake()->image('new.png', 200, 200)]],
    ]))->assertSessionHas('success');

    $newPhoto = $application->practitionerProfile()->value('photo_path');
    expect($newPhoto)->not->toBe($oldPhoto);
    Storage::disk('local')->assertExists($newPhoto);
    Storage::disk('local')->assertMissing($oldPhoto);
    expect($form->fresh()->status)->toBe('pending');
});

test('Save Approvals sends the applicant to interview once every document is approved', function () {
    $application = pracSubmit($this);
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.start', $application));

    $documents = $application->documents()->get()->values();
    $payload   = fn (string $last) => ['evaluations' => $documents->map(fn ($doc, $i) => [
        'id'     => $doc->id,
        'status' => $i === $documents->count() - 1 ? $last : 'approved',
    ])->all()];

    // One still pending: nothing is finalized.
    pracAs($this, $this->accVerifier)
        ->postJson(route('admin.accreditation.practitioner.finalize_evaluation', $application), $payload('pending'))
        ->assertStatus(422);
    expect($application->pctEntries()->where('step_number', 3)->first()->completed_at)->toBeNull();

    pracAs($this, $this->accVerifier)
        ->postJson(route('admin.accreditation.practitioner.finalize_evaluation', $application), $payload('approved'))
        ->assertOk()->assertJson(['success' => true, 'action' => 'proceed_to_interview', 'new_status' => 'Scheduled for Interview']);

    expect($application->fresh()->latestStatus->status->name)->toBe('Scheduled for Interview');
    expect($application->documents()->where('status', '!=', 'approved')->count())->toBe(0);

    // PCT: Step 3 done; Step 4 (Set up Interview Schedule, 3 days) is the
    // applicant's, so it is checked with no time counted.
    $steps = $application->pctEntries()->get()->keyBy('step_number');
    expect($steps[3]->completed_at)->not->toBeNull()
        ->and($steps[4]->step_name)->toBe('Set up Interview Schedule')
        ->and($steps[4]->target_days)->toBe(3)
        ->and($steps[4]->completed_at)->not->toBeNull()
        ->and($steps[4]->totalElapsedSeconds())->toBe(0);

    // The applicant is told to choose a slot on the tracking page.
    expect(pracMailed(InterviewSlotSelectionEmail::class, 'practitioner@example.com'))->toBeTrue();

    // The evaluation is closed.
    pracAs($this, $this->accVerifier)
        ->postJson(route('admin.accreditation.practitioner.evaluate_item', $application), ['item_type' => 'document', 'item_id' => $documents->first()->id, 'status' => 'rejected'])
        ->assertStatus(422);
});

test('the application page lists the form with the documents and runs the live PCT timer once started', function () {
    $application = pracSubmit($this);

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.new.pending'))
        ->assertOk()->assertSee($application->tracking_number)->assertSee('Start Evaluation');

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()
        ->assertSee('Application Form (DOLE-BWC AF-PCN-A1)')
        ->assertDontSee('Process Cycle Time (PCT)')
        ->assertSee('startEvalModal' . $application->id)
        ->assertDontSee('btn-approve');

    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.start', $application));

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()
        ->assertSee('Process Cycle Time (PCT)')
        ->assertSee('id="livePctCounter"', false)
        ->assertSee('btn-approve')
        ->assertSee('id="btn-open-schedule"', false)
        ->assertSee('js/evaluation.js');

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.application_form', $application))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('an application submitted before the form was filed as a document gets it attached', function () {
    $application = pracSubmit($this);
    $application->documents()->whereHas('documentField', fn ($q) => $q->where('code', 'PRAC_00'))->delete();
    expect($application->documents()->count())->toBe(6);

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()->assertSee('Application Form (DOLE-BWC AF-PCN-A1)');

    expect($application->documents()->count())->toBe(7);
});

test('the divisions are kept apart', function () {
    $application = pracSubmit($this);

    // HCD's Team Lead has no access to the Accreditation division's pages…
    pracAs($this, $this->hcdTeamLead)
        ->get(route('admin.accreditation.practitioner.new.pending'))
        ->assertForbidden();

    // …HCD's lists leave practitioner applications out…
    pracAs($this, $this->hcdTeamLead)
        ->get(route('admin.hcd.applications.pending'))
        ->assertOk()->assertDontSee($application->tracking_number);

    // …and HCD's own endpoints refuse one.
    pracAs($this, $this->hcdTeamLead)
        ->get(route('admin.hcd.applications.show', $application))
        ->assertNotFound();

    // The Accreditation Team Lead is not HCD's Team Lead.
    $fatpro = Application::create([
        'user_id'               => $application->user_id,
        'accreditation_type_id' => config('accreditation.types.fatpro'),
        'application_type'      => 'new',
        'tracking_number'       => 'ARMS2026-FP0001',
    ]);
    pracSetStatus($fatpro, 'Submitted');

    pracAs($this, $this->accTeamLead)
        ->post(route('admin.hcd.applications.update_evaluation', $fatpro), ['evaluator_id' => $this->accVerifier->id])
        ->assertForbidden();
});

test('a FATPro submission notifies HCD Team Leads only', function () {
    $pending = PendingRegistration::create([
        'token'                 => str_repeat('a', 64),
        'email'                 => 'fatpro@example.com',
        'password'              => bcrypt('Secret1234'),
        'profile_type'          => 'Organization',
        'accreditation_type_id' => config('accreditation.types.fatpro'),
        'form_data'             => ['org_name' => 'Aid Co', 'org_address' => 'Manila', 'head_name' => 'Head', 'designation' => 'CEO', 'org_email' => 'org@example.com',
                                    'rep_full_name' => 'Rep', 'rep_position' => 'Officer', 'rep_contact_number' => '09170000000', 'rep_email' => 'rep@example.com'],
        'documents_data'        => [],
        'instructors_data'      => [],
        'expires_at'            => now()->addHour(),
    ]);

    $this->get(route('register.verify', $pending->token))->assertOk();

    expect(pracMailed(AdminApplicationSubmittedEmail::class, 'hcdarms2026@gmail.com'))->toBeTrue();
    expect(pracMailed(AdminApplicationSubmittedEmail::class, 'oshc1987accreditation@gmail.com'))->toBeFalse();
});

test('the sample seeder creates one pending practitioner and one pending consultant, ready to start', function () {
    $emails = ['queeniefrancisco2002@gmail.com', 'queenief102702@gmail.com'];
    // A retired sample from an earlier run is removed.
    User::create([
        'email' => 'testpractitioner1@example.com', 'password' => 'Password123!', 'role_id' => 1, 'profile_type' => 'Individual',
    ]);

    $this->seed(\Database\Seeders\PractitionerTestApplicationSeeder::class);

    expect(User::where('email', 'testpractitioner1@example.com')->exists())->toBeFalse();

    $applications = Application::with('user', 'documents.documentField')->whereHas('user', fn ($q) => $q->whereIn('email', $emails))->get();
    expect($applications)->toHaveCount(2);

    foreach ($applications as $application) {
        expect($application->latestStatus->status->name)->toBe('Submitted');
        Storage::disk('local')->assertExists($application->practitionerProfile->photo_path);
        Storage::disk('local')->assertExists($application->practitionerProfile->application_form_path);
        expect($application->pctEntries()->count())->toBe(0);
    }

    $practitioner = $applications->firstWhere('user.email', 'queeniefrancisco2002@gmail.com');
    $consultant   = $applications->firstWhere('user.email', 'queenief102702@gmail.com');
    expect($practitioner->isConsultant())->toBeFalse()
        ->and($consultant->isConsultant())->toBeTrue()
        // The consultant's own checklist, its form generated (not a sample PDF) as CONS_00.
        ->and($consultant->documents->pluck('documentField.code')->sort()->values()->all())
        ->toBe(['CONS_00', 'CONS_01', 'CONS_02', 'CONS_03', 'CONS_04', 'CONS_05', 'CONS_06'])
        ->and($consultant->documents->firstWhere('documentField.code', 'CONS_00')->userDocument->file_path)
        ->toBe($consultant->practitionerProfile->application_form_path);

    // Re-running replaces the samples instead of duplicating them.
    $this->seed(\Database\Seeders\PractitionerTestApplicationSeeder::class);
    expect(User::whereIn('email', $emails)->count())->toBe(2)
        ->and(Application::whereIn('accreditation_type_id', [1, 2])->count())->toBe(2);

    $practitioner = Application::whereHas('user', fn ($q) => $q->where('email', $emails[0]))->firstOrFail();
    $consultant   = Application::whereHas('user', fn ($q) => $q->where('email', $emails[1]))->firstOrFail();

    pracAs($this, $this->accTeamLead)->get(route('admin.accreditation.practitioner.new.pending'))
        ->assertOk()->assertViewHas('applications', fn ($apps) => $apps->pluck('id')->all() === [$practitioner->id]);
    pracAs($this, $this->accTeamLead)->get(route('admin.accreditation.consultant.new.pending'))
        ->assertOk()->assertViewHas('applications', fn ($apps) => $apps->pluck('id')->all() === [$consultant->id]);

    pracAs($this, $this->accTeamLead)
        ->post(route('admin.accreditation.practitioner.start', $consultant))
        ->assertRedirect(route('admin.accreditation.practitioner.show', $consultant));
});

test('the Accreditation dashboard breaks out practitioner and consultant counts', function () {
    $application = pracSubmit($this);

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.dashboard'))
        ->assertOk()
        ->assertSee('OSH Practitioner &amp; OSH Consultant', false)
        ->assertSee('Practitioner: 1 Pending · 0 Under Review')
        ->assertSee('Consultant: 0 Pending · 0 Under Review');

    pracSetStatus($application, 'Rejected');
    \App\Services\CacheService::bustApplicationCaches();

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.dashboard'))
        ->assertOk()
        ->assertSee('Practitioner: 0 Pending · 0 Under Review')
        ->assertSee('Practitioner: 1 · Consultant: 0');
});

test('the division lists open for Accreditation admins only and hold practitioner applications HCD does not see', function () {
    $application = pracSubmit($this);
    pracSetStatus($application, 'Scheduled for Interview');

    $routes = [
        'admin.accreditation.division.interviews.slots',
        'admin.accreditation.division.interviews.scheduled',
        'admin.accreditation.division.accreditations.active',
        'admin.accreditation.division.accreditations.inactive',
        'admin.accreditation.division.applications.archived',
    ];

    foreach ($routes as $name) {
        pracAs($this, $this->accVerifier)->get(route($name))->assertOk();
        pracAs($this, $this->hcdTeamLead)->get(route($name))->assertForbidden();
    }

    // Approved and still choosing: listed on Manage Interview Slots, in the
    // same Pending for Interview table as Scheduled Interviews.
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.division.interviews.slots'))
        ->assertSee('id="slots_pending_interview_table"', false)
        ->assertSee('Pending for Interview')
        ->assertSee($application->tracking_number)
        ->assertSee(route('admin.accreditation.practitioner.show', $application->id), false);

    $hcdEvaluator = User::where('email', 'data@oshc.dole.gov.ph')->firstOrFail();
    pracAs($this, $hcdEvaluator)->get(route('admin.hcd.interviews.pending'))
        ->assertOk()->assertDontSee($application->tracking_number);
});

test('Recommendation / Payment covers every type for Accreditation but stays FATPro-only for HCD', function () {
    $application = pracSubmit($this);
    pracSetStatus($application, 'Awaiting Payment');
    \App\Services\CacheService::bustApplicationCaches();

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.applications.awaiting_payment'))
        ->assertOk()
        ->assertSee($application->tracking_number)
        ->assertSee(route('admin.accreditation.practitioner.show', $application));

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.dashboard'))
        ->assertOk()->assertSee('All Accreditations');

    $hcdEvaluator = User::where('email', 'data@oshc.dole.gov.ph')->firstOrFail();
    pracAs($this, $hcdEvaluator)->get(route('admin.hcd.applications.awaiting_payment'))
        ->assertOk()->assertDontSee($application->tracking_number);
});

test('the Accreditation sidebar shows Practitioner and a locked Consultant section', function () {
    $html = pracAs($this, $this->accTeamLead)
        ->get(route('admin.accreditation.practitioner.new.pending'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('OSH Practitioner')
        ->toContain('OSH Consultant')
        ->toContain('nav-locked')
        ->toContain(route('admin.accreditation.practitioner.new.under_review'))
        ->toContain('Manage Interview Slots')
        ->not->toContain('Pending to Schedule');
});

test('Accreditation admins open interview slots on several dates with preset and custom times', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-02 08:00:00')); // a Monday

    // Each date carries its own mode and venue; a plain date string is a
    // face-to-face day at the OSHC.
    $store = fn (array $data) => pracAs($this, $this->accTeamLead)
        ->post(route('admin.accreditation.division.interviews.slots.store'), ['dates' => array_map(
            fn ($d) => is_array($d) ? $d : ['date' => $d, 'mode' => 'f2f', 'venue' => 'OSHC, Quezon City'],
            $data['dates'] ?? [],
        )] + $data);

    $store([
        'dates'   => ['2026-11-05', '2026-11-06', '2026-11-05'], // a repeated date counts once
        'presets' => ['09:00-10:30', '13:00-14:30'],
        'custom'  => [['start' => '16:00', 'end' => '17:00'], ['start' => '', 'end' => '']],
    ])->assertRedirect(route('admin.accreditation.division.interviews.slots'))->assertSessionHas('success');

    // 2 dates × (2 presets + 1 custom)
    expect(InterviewSlot::count())->toBe(6);
    expect(InterviewSlot::whereDate('slot_date', '2026-11-05')->where('start_time', '16:00:00')->where('end_time', '17:00:00')->exists())->toBeTrue();

    // The same times again open nothing new.
    $store(['dates' => ['2026-11-05'], 'presets' => ['09:00-10:30']])->assertSessionHas('error');
    expect(InterviewSlot::count())->toBe(6);

    // Today's times that already passed are skipped; later ones open.
    Carbon::setTestNow(Carbon::parse('2026-11-02 11:00:00'));
    $store(['dates' => ['2026-11-02'], 'presets' => ['09:00-10:30', '13:00-14:30']])->assertSessionHas('success');
    expect(InterviewSlot::whereDate('slot_date', '2026-11-02')->pluck('start_time')->all())->toBe(['13:00:00']);

    // Validation.
    $store(['dates' => ['2026-11-07']])->assertSessionHasErrors('presets');
    $store(['dates' => ['2026-10-30'], 'presets' => ['09:00-10:30']])->assertSessionHasErrors('dates.0.date');
    $store(['dates' => ['2026-11-07'], 'custom' => [['start' => '15:00', 'end' => '14:00']]])->assertSessionHasErrors('custom.0.end');
    $store(['dates' => ['2026-11-07'], 'custom' => [['start' => '15:00', 'end' => '']]])->assertSessionHasErrors('custom.0.start');
    $store(['dates' => [], 'presets' => ['09:00-10:30']])->assertSessionHasErrors('dates');

    // Face-to-face needs an address — not a meeting link left over from
    // switching modes — and online needs a real link.
    $store(['dates' => [['date' => '2026-11-09', 'mode' => 'f2f', 'venue' => 'https://www.zoom.com']], 'presets' => ['09:00-10:30']])
        ->assertSessionHasErrors(['dates.0.venue' => 'The face-to-face interviews on Nov 09, 2026 need the venue address, not a meeting link.']);
    // The added dates come back with their own mode and venue to fix.
    pracAs($this, $this->accTeamLead)
        ->withSession(['_old_input' => ['dates' => [['date' => '2026-11-09', 'mode' => 'f2f', 'venue' => 'https://www.zoom.com']]]])
        ->get(route('admin.accreditation.division.interviews.slots'))
        ->assertSee('name="dates[0][date]" value="2026-11-09"', false)
        ->assertSee('name="dates[0][mode]" value="f2f"', false)
        ->assertSee('name="dates[0][venue]" value="https://www.zoom.com"', false)
        ->assertSee('Mon, Nov 09, 2026');
    $store(['dates' => [['date' => '2026-11-09', 'mode' => 'online', 'venue' => 'OSHC, Quezon City']], 'presets' => ['09:00-10:30']])
        ->assertSessionHasErrors('dates.0.venue');
    $store(['dates' => [['date' => '2026-11-09', 'mode' => 'nope', 'venue' => 'OSHC, Quezon City']], 'presets' => ['09:00-10:30']])
        ->assertSessionHasErrors('dates.0.mode');

    // One batch may mix modes: each date keeps the mode and venue it was
    // added with (an online day, then a face-to-face day).
    $store([
        'dates' => [
            ['date' => '2026-11-09', 'mode' => 'online', 'venue' => 'https://zoom.us/j/123456'],
            ['date' => '2026-11-10', 'mode' => 'f2f', 'venue' => 'OSHC, Quezon City'],
        ],
        'presets' => ['09:00-10:30', '10:30-12:00'],
    ])->assertSessionHas('success');
    expect(InterviewSlot::whereDate('slot_date', '2026-11-09')->pluck('mode')->unique()->all())->toBe(['online'])
        ->and(InterviewSlot::whereDate('slot_date', '2026-11-09')->value('venue'))->toBe('https://zoom.us/j/123456')
        ->and(InterviewSlot::whereDate('slot_date', '2026-11-10')->pluck('mode')->unique()->all())->toBe(['f2f'])
        ->and(InterviewSlot::whereDate('slot_date', '2026-11-10')->value('venue'))->toBe('OSHC, Quezon City');

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.division.interviews.slots'))
        ->assertOk()
        ->assertSee('Open Interview Slots')
        ->assertSee('9:00 AM – 10:30 AM')
        ->assertSee('4:00 PM – 5:00 PM')
        // Remove asks in a modal, not the browser's confirm box.
        ->assertSee('id="slotRemoveModal"', false)
        ->assertSee('data-slot-remove-action=', false)
        ->assertDontSee('data-prac-confirm', false);

    // Any admin in the division may remove an open slot; HCD may not touch them.
    $slot = InterviewSlot::firstOrFail();
    pracAs($this, $this->hcdTeamLead)
        ->delete(route('admin.accreditation.division.interviews.slots.destroy', $slot))
        ->assertForbidden();
    pracAs($this, $this->accVerifier)
        ->delete(route('admin.accreditation.division.interviews.slots.destroy', $slot))
        ->assertSessionHas('success');
    expect(InterviewSlot::find($slot->id))->toBeNull();
});

test('an approved applicant books an open slot on the tracking page and every Accreditation admin is emailed', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-02 08:00:00'));

    $application = pracSubmit($this);
    pracApproveAll($this, $application);

    $first  = pracSlot('2026-11-05', '09:00', '10:30');
    $second = pracSlot('2026-11-05', '10:30', '12:00', ['mode' => 'online', 'venue' => 'https://meet.example.com/oshc']);
    $past   = pracSlot('2026-10-30', '09:00', '10:30');

    $this->get(route('track', ['tracking_number' => $application->tracking_number]))
        ->assertOk()
        ->assertSee('Choose Your Interview Schedule')
        ->assertSee('Thursday, November 5, 2026')
        ->assertSee('9:00 AM – 10:30 AM')
        ->assertSee('Thursday, November 5, 2026</strong>', false)
        ->assertSee('&quot;2026-11-05&quot;:&quot;open&quot;', false)
        ->assertSee('value="' . $first->id . '"', false)
        // Confirming asks in a modal, which reads the slot from the radio.
        ->assertSee('id="tiConfirmModal"', false)
        ->assertSee('data-ti-time-label="9:00 AM – 10:30 AM"', false)
        ->assertDontSee('value="' . $past->id . '"', false);

    // Until a slot is chosen the applicant is Pending for Interview on Manage
    // Interview Slots, not on Scheduled Interviews.
    $listed = fn ($apps) => $apps->contains('id', $application->id);
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.division.interviews.slots'))
        ->assertOk()
        ->assertSee('Pending for Interview')
        ->assertViewHas('awaiting', $listed);
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.division.interviews.scheduled'))
        ->assertOk()
        ->assertDontSee('Pending for Interview')
        ->assertViewMissing('pending')
        ->assertViewHas('applications', fn ($apps) => ! $listed($apps));

    $this->post(route('track.interview.book'), ['tracking_number' => $application->tracking_number, 'slot_id' => $first->id])
        ->assertSessionHas('success');

    $first->refresh();
    expect($first->application_id)->toBe($application->id)
        ->and($first->booked_at)->not->toBeNull();

    $interview = $application->fresh()->interview;
    expect($interview->interview_date->format('Y-m-d'))->toBe('2026-11-05')
        ->and($interview->interview_time)->toBe('09:00:00')
        ->and($interview->mode)->toBe('f2f')
        ->and($interview->venue)->toBe('Occupational Safety and Health Center');

    // The applicant gets the confirmation; every Accreditation admin — and
    // no one in HCD — is emailed what was booked.
    expect(pracMailed(InterviewScheduleEmail::class, 'practitioner@example.com'))->toBeTrue();
    expect(pracMailed(AdminInterviewSlotBookedEmail::class, 'oshc1987accreditation@gmail.com'))->toBeTrue();
    expect(pracMailed(AdminInterviewSlotBookedEmail::class, 'kevin.goto@oshc.dole.gov.ph'))->toBeTrue();
    expect(pracMailed(AdminInterviewSlotBookedEmail::class, 'hcdarms2026@gmail.com'))->toBeFalse();

    // Both new emails render.
    expect((new InterviewSlotSelectionEmail($application))->render())->toContain('Choose My Interview Schedule');
    $booked = new AdminInterviewSlotBookedEmail($application->fresh(), $first);
    expect($booked->envelope()->subject)->toBe('[Admin Notification] Interview Schedule Booked — ' . $application->tracking_number);
    expect($booked->render())
        ->toContain('Interview Schedule Booked')
        ->toContain('Thursday, November 5, 2026')
        ->toContain('9:00 AM – 10:30 AM')
        ->toContain('OSH Practitioner')
        ->toContain('Application Type:</strong> New')
        ->toContain(route('admin.accreditation.practitioner.show', $application->id));

    // One booking per applicant.
    $this->post(route('track.interview.book'), ['tracking_number' => $application->tracking_number, 'slot_id' => $second->id])
        ->assertSessionHas('error');
    expect($second->fresh()->application_id)->toBeNull();

    // The tracking page now shows the schedule instead of the picker.
    $this->get(route('track', ['tracking_number' => $application->tracking_number]))
        ->assertOk()
        ->assertSee('Your Interview Has Been Scheduled')
        ->assertDontSee('Choose Your Interview Schedule');

    // Admin side: on the application, in Scheduled Interviews, and the booked slot stays.
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()->assertSee('Chosen by the applicant');
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.division.interviews.scheduled'))
        ->assertOk()->assertSee($application->tracking_number)->assertSee('9:00 AM')
        ->assertViewHas('applications', $listed);
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.division.interviews.slots'))
        ->assertViewHas('awaiting', fn ($apps) => ! $listed($apps));
    pracAs($this, $this->accVerifier)
        ->delete(route('admin.accreditation.division.interviews.slots.destroy', $first))
        ->assertSessionHas('error');
    expect(InterviewSlot::find($first->id))->not->toBeNull();
});

test('a taken slot, a past slot, or an application not yet for interview cannot be booked', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-02 08:00:00'));

    $first  = pracSubmit($this);
    $second = pracSubmit($this, ['email' => 'second@example.com']);
    $third  = pracSubmit($this, ['email' => 'third@example.com']);
    pracApproveAll($this, $first);
    pracApproveAll($this, $second);

    $slot = pracSlot('2026-11-05', '09:00', '10:30');
    $past = pracSlot('2026-10-30', '09:00', '10:30');

    $book = fn (Application $app, InterviewSlot $s) => $this->post(route('track.interview.book'), ['tracking_number' => $app->tracking_number, 'slot_id' => $s->id]);

    $book($first, $slot)->assertSessionHas('success');

    // Taken.
    $book($second, $slot)->assertSessionHas('error');
    expect($slot->fresh()->application_id)->toBe($first->id)
        ->and($second->fresh()->interview)->toBeNull();

    // The other applicant still sees it on the calendar, as Fully Booked.
    $this->get(route('track', ['tracking_number' => $second->tracking_number]))
        ->assertOk()
        ->assertSee('Fully Booked')
        ->assertSee('No available date')
        ->assertSee('&quot;2026-11-05&quot;:&quot;full&quot;', false)
        ->assertSeeInOrder(['value="' . $slot->id . '"', 'disabled'], false)
        ->assertDontSee('value="' . $past->id . '"', false);

    // Already past.
    $book($second, $past)->assertSessionHas('error');
    expect($past->fresh()->application_id)->toBeNull();

    // Documents not yet approved.
    $open = pracSlot('2026-11-06', '09:00', '10:30');
    $book($third, $open)->assertSessionHas('error');
    expect($open->fresh()->application_id)->toBeNull();

    // A FATPro application never books here (HCD schedules its interviews).
    $fatpro = Application::create([
        'user_id'               => $third->user_id,
        'accreditation_type_id' => config('accreditation.types.fatpro'),
        'application_type'      => 'new',
        'tracking_number'       => 'ARMS2026-FP0002',
    ]);
    pracSetStatus($fatpro, 'Scheduled for Interview');
    $book($fatpro, $open)->assertSessionHas('error');
    expect($open->fresh()->application_id)->toBeNull();
});

/** Start the evaluation and reject the first document: the applicant has 5 working days. */
function pracRejectOne($test, Application $application): void
{
    pracAs($test, $test->accVerifier)->post(route('admin.accreditation.practitioner.start', $application));

    $documents = $application->documents()->get()->values();
    pracAs($test, $test->accVerifier)
        ->postJson(route('admin.accreditation.practitioner.finalize_evaluation', $application), [
            'evaluations' => $documents->map(fn ($doc, $i) => [
                'id'      => $doc->id,
                'status'  => $i === 0 ? 'rejected' : 'approved',
                'remarks' => $i === 0 ? 'Unsigned' : null,
            ])->all(),
        ])
        ->assertJson(['action' => 'rejection_sent']);

    CacheService::bustTrackingCache($application->tracking_number);
}

/** Approve, book a slot and return it. */
function pracBook($test, Application $application, array $slotAttributes = []): InterviewSlot
{
    pracApproveAll($test, $application);
    $slot = pracSlot('2026-11-05', '09:00', '10:30', $slotAttributes);

    $test->post(route('track.interview.book'), ['tracking_number' => $application->tracking_number, 'slot_id' => $slot->id])
        ->assertSessionHas('success');

    return $slot;
}

test('rejected documents must be re-uploaded within 5 working days or the application is archived for a month, then deleted', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00')); // Tuesday

    $application = pracSubmit($this);
    pracRejectOne($this, $application);

    // Wed 4, Thu 5, Fri 6, Mon 9, Tue 10.
    expect($application->fresh()->compliance_due_date->toDateString())->toBe('2026-11-10');

    $statesDeadline = fn ($mail) => $mail->hasTo('practitioner@example.com') && $mail->complianceDueDate?->toDateString() === '2026-11-10';
    expect(Mail::queued(DocumentRejectionEmail::class, $statesDeadline)->isNotEmpty() || Mail::sent(DocumentRejectionEmail::class, $statesDeadline)->isNotEmpty())->toBeTrue();
    expect((new DocumentRejectionEmail($application->fresh(), collect()))->withComplianceDeadline(Carbon::parse('2026-11-10'), 1)->render())
        ->toContain('5 working days')->toContain('Tuesday, November 10, 2026')->toContain('after 1 month');

    $this->get(route('track', ['tracking_number' => $application->tracking_number]))
        ->assertOk()->assertSee('Re-upload Deadline: Tuesday, November 10, 2026');
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()->assertSee('Re-upload deadline');

    // The applicant has the whole last day.
    Carbon::setTestNow(Carbon::parse('2026-11-10 20:00:00'));
    $this->artisan('practitioner:enforce-deadlines')->assertSuccessful();
    expect($application->fresh()->archived_at)->toBeNull();

    Carbon::setTestNow(Carbon::parse('2026-11-11 08:00:00'));
    $this->artisan('practitioner:enforce-deadlines')->assertSuccessful();

    $application->refresh();
    expect($application->latestStatus->status->name)->toBe('Rejected')
        ->and($application->archive_reason)->toBe('non_compliance')
        ->and($application->reapply_after->toDateString())->toBe('2026-12-11')
        ->and($application->compliance_due_date)->toBeNull()
        ->and($application->pctEntries()->whereNull('completed_at')->count())->toBe(0);
    expect(pracMailed(PractitionerApplicationArchivedEmail::class, 'practitioner@example.com'))->toBeTrue();
    expect((new PractitionerApplicationArchivedEmail($application))->render())
        ->toContain('Your Application Has Been Archived')->toContain('December 11, 2026');

    $this->get(route('track', ['tracking_number' => $application->tracking_number]))
        ->assertOk()->assertSee('Application Archived')->assertSee('December 11, 2026');
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.division.applications.archived'))
        ->assertOk()->assertSee($application->tracking_number)->assertSee('Documents not re-uploaded');

    // No new application for a month — by email, or by name and birthday.
    $this->postJson(route('register.store'), pracPayload())
        ->assertStatus(422)->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'December 11, 2026'));
    $this->postJson(route('register.store'), pracPayload(['email' => 'other@example.com']))
        ->assertStatus(422)->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, $application->tracking_number));
    $this->postJson(route('register.store'), pracPayload(['email' => 'stranger@example.com', 'first_name' => 'Maria']))
        ->assertOk();

    // A month on, the archived record is deleted and the email is free again.
    $userId = $application->user_id;
    Carbon::setTestNow(Carbon::parse('2026-12-11 08:00:00'));
    $this->artisan('practitioner:enforce-deadlines')->assertSuccessful();

    expect(Application::find($application->id))->toBeNull()
        ->and(User::find($userId))->toBeNull();
    $this->post(route('register.store'), pracPayload())->assertOk()->assertJson(['status' => 'pending']);
});

test('a re-upload after the deadline is refused and archives the application; one in time clears the deadline', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $late   = pracSubmit($this);
    $onTime = pracSubmit($this, ['email' => 'second@example.com', 'first_name' => 'Pedro']);
    pracRejectOne($this, $late);
    pracRejectOne($this, $onTime);

    $reupload = fn (Application $app) => $this->post(route('track.resubmit.all'), [
        'tracking_number' => $app->tracking_number,
        'files'           => [$app->documents()->where('status', 'rejected')->value('id') => UploadedFile::fake()->create('fixed.pdf', 40, 'application/pdf')],
    ]);

    Carbon::setTestNow(Carbon::parse('2026-11-10 16:00:00'));
    $reupload($onTime)->assertSessionHas('success');
    expect($onTime->fresh()->compliance_due_date)->toBeNull()
        ->and($onTime->fresh()->latestStatus->status->name)->toBe('Under Evaluation');

    Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00'));
    $reupload($late)->assertSessionHas('error');
    expect($late->fresh()->latestStatus->status->name)->toBe('Rejected')
        ->and($late->fresh()->archive_reason)->toBe('non_compliance');

    // The daily run leaves the one that complied alone.
    $this->artisan('practitioner:enforce-deadlines')->assertSuccessful();
    expect($onTime->fresh()->archived_at)->toBeNull();
});

test('the panel interview starts, stops (PCT Step 6 starts) and records a pass: For Recommendation, applicant congratulated', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracBook($this, $application, ['mode' => 'online', 'venue' => 'meet.example.com/oshc-panel']);

    // Booking opens Step 5 (Panel Interview, 2 hours), paused until the slot.
    $step5 = $application->pctEntries()->where('step_number', 5)->first();
    expect($step5->step_name)->toBe('Panel Interview')
        ->and($step5->stepStatus())->toBe('paused');

    $show = fn () => pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application));

    // FATPro's interview card: the schedule chips and Start Interview, which
    // opens the meeting link.
    $show()->assertOk()
        ->assertSee('Interview Schedule Details')
        ->assertSee('Open Link')
        ->assertSee('The interview is scheduled. Click below when it actually begins:')
        ->assertSee('id="start-interview-form"', false)
        ->assertSee('data-interview-mode="online"', false)
        ->assertSee('data-interview-link="https://meet.example.com/oshc-panel"', false)
        ->assertSee('data-max-interview-seconds="7200"', false)
        ->assertSee('Waiting for scheduled interview')
        ->assertSee('days / 2 hours', false)
        ->assertSee('3 days 4 hours target')
        ->assertSee('2 days 3 hours 30 minutes target');

    // A result cannot be recorded before the interview is stopped.
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.interview_result', $application), ['result' => 'passed'])
        ->assertSessionHas('error');

    Carbon::setTestNow(Carbon::parse('2026-11-03 10:00:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.start_interview', $application))
        ->assertSessionHas('success');
    expect($step5->fresh()->stepStatus())->toBe('active');

    $show()->assertOk()
        ->assertSee('Interview is currently running')
        ->assertSee('id="stop-interview-form"', false)
        ->assertSee('data-target-label="2 hours"', false);

    // Stop: Step 5 completes and Step 6 (Interview Result and Recommendation) starts.
    Carbon::setTestNow(Carbon::parse('2026-11-03 11:15:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.stop_interview', $application))
        ->assertSessionHas('success');
    expect($step5->fresh()->completed_at)->not->toBeNull()
        ->and($step5->fresh()->elapsed_seconds)->toBe(4500);

    $step6 = $application->pctEntries()->where('step_number', 6)->first();
    expect($step6->step_name)->toBe('Interview Result and Recommendation')
        ->and($step6->stepStatus())->toBe('active');

    $show()->assertOk()
        ->assertSee('Please record the outcome of the scheduled interview:')
        ->assertSee('passedConfirmModal')->assertSee('notPassedConfirmModal');

    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.interview_result', $application), ['result' => 'passed'])
        ->assertRedirect(route('admin.accreditation.practitioner.show', $application));

    // For Recommendation; the applicant is congratulated, Step 6 keeps running.
    expect($application->fresh()->latestStatus->status->name)->toBe('For Recommendation')
        ->and($step6->fresh()->stepStatus())->toBe('active');
    expect(pracMailed(\App\Mail\PractitionerInterviewPassedEmail::class, 'practitioner@example.com'))->toBeTrue();
    expect((new \App\Mail\PractitionerInterviewPassedEmail($application->fresh()))->render())
        ->toContain('Congratulations! You Passed the Interview')->toContain('OSH Practitioner')->toContain('450.00 Pesos');

    $summary = app(PctService::class)->getSummary($application->fresh());
    expect($summary['total_target'])->toBe(18.2)
        ->and(collect($summary['steps'])->firstWhere('number', 7)['target_label'])->toBe('2 days 3 hours 30 minutes');

    $show()->assertOk()->assertSee('Interview Result: Passed')->assertSee('Generate Recommendation Form');
    $this->get(route('track', ['tracking_number' => $application->tracking_number]))
        ->assertOk()->assertSee('Interview Passed — For Recommendation');
    // Listed under Recommendation/Payment — there is no separate practitioner
    // For Recommendation page — and the application page points back there.
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.applications.awaiting_payment'))
        ->assertOk()->assertSee($application->tracking_number)->assertDontSee('practitioner/for-recommendation');
    $show()->assertSee(route('admin.accreditation.applications.awaiting_payment'));
});

test('the interview starts on its own at the slot time and stops itself after 2 hours', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracBook($this, $application);

    $show = fn () => pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application));
    $step = fn () => $application->pctEntries()->where('step_number', 5)->first();

    Carbon::setTestNow(Carbon::parse('2026-11-05 08:59:00'));
    $show()->assertOk();
    expect($step()->stepStatus())->toBe('paused');

    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00'));
    $show()->assertOk()->assertSee('Interview is currently running');
    expect($step()->stepStatus())->toBe('active');

    Carbon::setTestNow(Carbon::parse('2026-11-05 11:05:00'));
    $show()->assertOk()->assertSee('Please record the outcome of the scheduled interview:');
    expect($step()->completed_at)->not->toBeNull()
        ->and($step()->elapsed_seconds)->toBe(7200)
        ->and($application->pctEntries()->where('step_number', 6)->first()?->stepStatus())->toBe('active');
});

test('recommendation form, signed scan, payment instructions, proof of payment and its validation, with PCT Step 7', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracBook($this, $application);

    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.start_interview', $application));
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:30:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.stop_interview', $application));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.interview_result', $application), ['result' => 'passed']);

    // Listed under Recommendation / Payment.
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.applications.awaiting_payment'))
        ->assertOk()->assertSee($application->tracking_number)->assertSee('For Recommendation');

    // FATPro's recommendation form template, practitioner box ticked.
    $details = [
        'date' => '2026-11-05', 'from' => 'Accreditation Division', 'to' => 'Office of the Executive Director (OED)',
        'specialization' => 'Construction', 'evaluator' => 'Kevin Goto', 'interviewers' => "Panel One\nPanel Two",
        'recommended_by' => 'DIVISION CHIEF', 'approved_by' => 'ENGR. JOSE MARIA S. BATINO',
    ];
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.generate_recommendation', $application), $details)
        ->assertRedirect(route('admin.accreditation.practitioner.generate_recommendation', $application));
    $this->get(route('admin.accreditation.practitioner.generate_recommendation', $application))
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    // Payment cannot be validated before there is one.
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.evaluate_payment', $application), ['proof_of_payment_status' => 'approved'])
        ->assertSessionHas('error');

    // Upload the signed scan: Awaiting Payment, Step 6 done, Step 7 paused, payment instructions emailed.
    Carbon::setTestNow(Carbon::parse('2026-11-06 10:00:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.upload_recommendation', $application), [
        'signed_recommendation_letter' => UploadedFile::fake()->create('signed.pdf', 80, 'application/pdf'),
    ])->assertSessionHas('success');

    $application->refresh();
    expect($application->latestStatus->status->name)->toBe('Awaiting Payment')
        ->and($application->payment->signed_recommendation_letter)->not->toBeNull()
        ->and($application->pctEntries()->where('step_number', 6)->first()->completed_at)->not->toBeNull();
    $step7 = fn () => $application->pctEntries()->where('step_number', 7)->first();
    expect($step7()->step_name)->toBe('Payment')
        ->and($step7()->stepStatus())->toBe('paused');

    $paymentEmail = fn ($mail) => $mail->hasTo('practitioner@example.com') && $mail->viewName === 'emails.payment_instructions';
    expect(Mail::queued(\App\Mail\ApplicationNoticeEmail::class, $paymentEmail)->isNotEmpty() || Mail::sent(\App\Mail\ApplicationNoticeEmail::class, $paymentEmail)->isNotEmpty())->toBeTrue();
    expect((new \App\Mail\ApplicationNoticeEmail($application, 'emails.payment_instructions', 'x'))->render())
        ->toContain('450.00 Pesos')->toContain('OSH Practitioner')->not->toContain('900.00');

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()->assertSee('View Signed Form')->assertSee('Waiting for the applicant to upload or resubmit Proof of Payment.');
    $this->get(route('track', ['tracking_number' => $application->tracking_number]))
        ->assertOk()->assertSee('450.00 Pesos')->assertSee('OSH Practitioner')->assertDontSee('900.00 Pesos');

    // The applicant uploads the proof: Payment Verification, Step 7 runs.
    Carbon::setTestNow(Carbon::parse('2026-11-09 09:00:00'));
    $mailedAdmins = collect();
    \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSent::class, function ($e) use ($mailedAdmins) {
        if ($e->channel === 'mail' && $e->notification instanceof \App\Notifications\PaymentSubmittedNotification) {
            $mailedAdmins->push($e->notifiable->email);
        }
    });
    $this->post(route('track.submit_payment'), [
        'tracking_number'  => $application->tracking_number,
        'proof_of_payment' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
    ])->assertSessionHas('success');
    expect($application->fresh()->latestStatus->status->name)->toBe('Payment Verification')
        ->and($step7()->stepStatus())->toBe('active');
    // Every Accreditation admin is emailed on QUEUE_CONNECTION ("sync"
    // here), not left in the jobs table waiting for a queue worker.
    expect($mailedAdmins->all())->toContain($this->accTeamLead->email, $this->accVerifier->email)
        ->and(\Illuminate\Support\Facades\DB::table('jobs')->count())->toBe(0);

    // Rejected: Step 7 pauses, the applicant is asked again.
    Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.evaluate_payment', $application), [
        'proof_of_payment_status' => 'rejected', 'proof_of_payment_remarks' => 'Blurred receipt',
    ])->assertSessionHas('success');
    expect($application->fresh()->latestStatus->status->name)->toBe('Awaiting Payment')
        ->and($step7()->stepStatus())->toBe('paused')
        ->and($step7()->elapsed_seconds)->toBe(3600);
    $rejection = fn ($mail) => $mail->hasTo('practitioner@example.com') && $mail->viewName === 'emails.payment_rejection';
    expect(Mail::queued(\App\Mail\ApplicationNoticeEmail::class, $rejection)->isNotEmpty() || Mail::sent(\App\Mail\ApplicationNoticeEmail::class, $rejection)->isNotEmpty())->toBeTrue();

    // Re-uploaded, then validated: Step 7 completes.
    $this->post(route('track.submit_payment'), [
        'tracking_number'  => $application->tracking_number,
        'proof_of_payment' => UploadedFile::fake()->create('receipt-clear.pdf', 40, 'application/pdf'),
    ])->assertSessionHas('success');
    expect($step7()->stepStatus())->toBe('active');

    Carbon::setTestNow(Carbon::parse('2026-11-09 11:30:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.evaluate_payment', $application), ['proof_of_payment_status' => 'approved'])
        ->assertSessionHas('success');
    expect($application->fresh()->payment->proof_of_payment_status)->toBe('approved')
        ->and($step7()->completed_at)->not->toBeNull()
        ->and($step7()->elapsed_seconds)->toBe(9000);

    // Approved and accredited: the practitioner sequence carries on from 1950.
    $application->refresh();
    expect($application->latestStatus->status->name)->toBe('Approved')
        ->and($application->accreditation->accreditation_number)->toBe('1033-261109-1951')
        ->and($application->accreditation->validity_date->toDateString())->toBe('2029-11-09')
        ->and($application->pctEntries()->where('step_number', 8)->first()->stepStatus())->toBe('active');
    expect(pracMailed(\App\Mail\ApplicationResultEmail::class, 'practitioner@example.com'))->toBeTrue();

    // (Checked on the list itself: the header's notifications also name it.)
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.applications.awaiting_payment'))
        ->assertOk()->assertViewHas('applications', fn ($apps) => ! $apps->contains('id', $application->id));
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.applications.releasing'))
        ->assertOk()->assertSee($application->tracking_number)->assertSee('1033-261109-1951');
});

/** Pass the interview and validate the payment: approved, with an accreditation number. */
function pracAccredit($test, Application $application): void
{
    pracBook($test, $application);
    pracAs($test, $test->accVerifier)->post(route('admin.accreditation.practitioner.start_interview', $application));
    pracAs($test, $test->accVerifier)->post(route('admin.accreditation.practitioner.stop_interview', $application));
    pracAs($test, $test->accVerifier)->post(route('admin.accreditation.practitioner.interview_result', $application), ['result' => 'passed']);
    pracAs($test, $test->accVerifier)->post(route('admin.accreditation.practitioner.upload_recommendation', $application), [
        'signed_recommendation_letter' => UploadedFile::fake()->create('signed.pdf', 80, 'application/pdf'),
    ]);
    $test->post(route('track.submit_payment'), [
        'tracking_number'  => $application->tracking_number,
        'proof_of_payment' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
    ]);
    pracAs($test, $test->accVerifier)->post(route('admin.accreditation.practitioner.evaluate_payment', $application), ['proof_of_payment_status' => 'approved'])
        ->assertSessionHas('success');
    CacheService::bustTrackingCache($application->tracking_number);
}

test('certificate issuance: generate the certificate, upload it with the ID card (PCT Step 8) and release them (PCT Step 9)', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $first  = pracSubmit($this);
    $second = pracSubmit($this, ['email' => 'second@example.com', 'first_name' => 'Pedro']);
    pracAccredit($this, $first);
    pracAccredit($this, $second);

    // Each practitioner gets the next number; FATPro's sequence is untouched.
    expect($first->fresh()->accreditation->accreditation_number)->toBe('1033-261103-1951')
        ->and($second->fresh()->accreditation->accreditation_number)->toBe('1033-261103-1952');

    $show = fn () => pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $first));
    $show()->assertOk()
        ->assertSee('1033-261103-1951')
        ->assertSee('View Certificate PDF')
        ->assertSee('Certificate Issuance')
        ->assertSee('Upload both the signed certificate and the ID card to release them.')
        ->assertSee('Prepare the Certificate of Accreditation and Identification Card')
        ->assertSee('days / 4 days 4 hours', false)
        ->assertSee('1 hour target');

    // The OSHC practitioner certificate: front and back on 8.5" x 13".
    $certData = [];
    \Illuminate\Support\Facades\View::composer('admin.accreditation.practitioner.certificate_pdf', function ($view) use (&$certData) {
        $certData = $view->getData();
    });
    $pdf = pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.certificate', [
        $first, 'executive_director' => 'JOSE MARIA S. BATINO', 'back_signatory' => 'Maria Santos', 'back_signatory_title' => 'OIC-Deputy Executive Director',
    ]))->assertOk()->assertHeader('content-type', 'application/pdf')->getContent();
    expect(preg_match_all('#/Type ?/Page(?!s)#', $pdf))->toBe(2)
        ->and($certData['practitionerName'])->toBe('Juan Dela Cruz')
        ->and($certData['honorific'])->toBe('Mr.')
        ->and($certData['field'])->toBe('Construction')
        ->and($certData['isRenewal'])->toBeFalse()
        ->and($certData['backSignatory'])->toBe('Maria Santos');

    $render = fn (bool $isRenewal) => view('admin.accreditation.practitioner.certificate_pdf', array_merge($certData, ['isRenewal' => $isRenewal]))->render();
    expect($render(false))
        ->toContain('CERTIFICATE OF ACCREDITATION')->toContain('No. 1033-261103-1951')
        ->toContain('OSH Practitioner')->toContain('in the field of')->toContain('Construction')
        ->toContain('is accredited by this Department as an Occupational Safety and Health Practitioner')
        ->toContain('Rules 1030 and 1974 of the Occupational Safety and Health Standards')
        ->toContain('<strong><u>OSH Practitioner.</u></strong>')->toContain('1x1 ID picture (red background)')
        ->toContain('certifies that <strong>Mr. Juan Dela Cruz</strong> has satisfactorily complied with all the requirements for accreditation as Occupational Safety and Health Practitioner')
        ->toContain('Maria Santos')->toContain('OIC-Deputy Executive Director')
        ->not->toContain('renewal of accreditation')->not->toContain('prior to this renewal')
        ->not->toContain('First Aid Training Provider');
    expect($render(true))
        ->toContain('all the requirements for the renewal of accreditation as Occupational Safety and Health Practitioner')
        ->toContain('<strong>Mr. Juan Dela Cruz</strong> has been an active OSH Practitioner for more than three (3) years prior to this renewal.');

    // Without the pop-up's values: the Executive Director and the position have
    // defaults, the OIC-Deputy Executive Director's name does not (it changes).
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.certificate', $first))->assertOk();
    expect($certData['backSignatory'])->toBe('')
        ->and($certData['backSignatoryTitle'])->toBe('OIC-Deputy Executive Director')
        ->and($certData['executiveDirector'])->toBe('JOSE MARIA S. BATINO');

    // Middle initial, suffix after a comma, Ms., and an "Other" industry's own text as the field.
    $first->user->individualProfile->update([
        'middle_name' => 'santos', 'suffix' => 'Jr.', 'sex' => 'Female', 'type_of_industry' => 'Other', 'type_of_industry_other' => 'Shipbuilding',
    ]);
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.certificate', $first))->assertOk();
    expect($certData['practitionerName'])->toBe('Juan S. Dela Cruz, Jr.')
        ->and($certData['honorific'])->toBe('Ms.')
        ->and($certData['field'])->toBe('Shipbuilding');
    // The pop-up: Executive Director predefined, the OIC-Deputy Executive Director blank and required.
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $first))
        ->assertOk()->assertSee('value="JOSE MARIA S. BATINO"', false)
        ->assertSee('name="back_signatory"' . "\n" . '                           value="" placeholder="Enter OIC-Deputy Executive Director name" autocomplete="off" required', false)
        ->assertSee('value="OIC-Deputy Executive Director"', false)
        ->assertDontSee('Evelyn C. Tandayu');

    // A release needs both files.
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.release', $first))->assertSessionHas('error');

    Carbon::setTestNow(Carbon::parse('2026-11-04 09:00:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.certificate_files.upload', $first), [
        'scanned_certificate' => UploadedFile::fake()->create('signed-certificate.pdf', 80, 'application/pdf'),
    ])->assertSessionHas('success');
    expect($first->pctEntries()->where('step_number', 8)->first()->completed_at)->toBeNull();

    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.certificate_files.upload', $first), [
        'id_card' => UploadedFile::fake()->image('id-card.jpg', 600, 380),
    ])->assertSessionHas('success');

    $accreditation = $first->fresh()->accreditation;
    expect($accreditation->scanned_certificate)->not->toBeNull()
        ->and($accreditation->id_card)->toEndWith('.jpg')
        ->and($first->pctEntries()->where('step_number', 8)->first()->completed_at)->not->toBeNull()
        ->and($first->pctEntries()->where('step_number', 9)->first()->stepStatus())->toBe('active');

    // Either file can be replaced until release. A failed save or a rejected
    // file keeps the current file and leaves nothing new behind.
    $upload = fn (array $files) => pracAs($this, $this->accVerifier)
        ->post(route('admin.accreditation.practitioner.certificate_files.upload', $first), $files);
    $disk     = Storage::disk('local');
    $oldCert  = $accreditation->scanned_certificate;
    $oldId    = $accreditation->id_card;
    $fileCount = count($disk->allFiles());

    $failSave = true;
    \App\Models\Accreditation::saving(function () use (&$failSave) {
        if ($failSave) {
            throw new RuntimeException('Save failed.');
        }
    });
    $upload(['scanned_certificate' => UploadedFile::fake()->create('new.pdf', 80, 'application/pdf')])
        ->assertSessionHas('error', 'The upload failed, so the current files were kept. Please try again.');
    $failSave = false;
    $upload(['scanned_certificate' => UploadedFile::fake()->image('not-a-pdf.jpg')])->assertSessionHasErrors('scanned_certificate');

    expect($accreditation->fresh()->scanned_certificate)->toBe($oldCert)
        ->and($disk->exists($oldCert))->toBeTrue()
        ->and(count($disk->allFiles()))->toBe($fileCount);

    // Saved: the new file takes over and the old one is deleted.
    $upload(['scanned_certificate' => UploadedFile::fake()->create('corrected.pdf', 80, 'application/pdf')])
        ->assertSessionHas('success', 'Signed certificate replaced. Release them to notify the applicant.');
    $accreditation->refresh();
    expect($accreditation->scanned_certificate)->not->toBe($oldCert)
        ->and($disk->exists($accreditation->scanned_certificate))->toBeTrue()
        ->and($disk->exists($oldCert))->toBeFalse()
        ->and($accreditation->id_card)->toBe($oldId)
        ->and($disk->exists($oldId))->toBeTrue()
        ->and(count($disk->allFiles()))->toBe($fileCount);

    // The ID card may change file type; both can go in one upload.
    $oldCert = $accreditation->scanned_certificate;
    $upload([
        'scanned_certificate' => UploadedFile::fake()->create('final.pdf', 80, 'application/pdf'),
        'id_card'             => UploadedFile::fake()->create('id-card.pdf', 80, 'application/pdf'),
    ])->assertSessionHas('success', 'Signed certificate and ID card replaced. Release them to notify the applicant.');
    $accreditation->refresh();
    expect($accreditation->id_card)->toEndWith('.pdf')
        ->and($disk->exists($oldCert))->toBeFalse()
        ->and($disk->exists($oldId))->toBeFalse()
        ->and(count($disk->allFiles()))->toBe($fileCount)
        // Replacing does not restart the PCT.
        ->and($first->pctEntries()->where('step_number', 9)->count())->toBe(1)
        ->and($first->statusLogs()->latest('id')->first()->remarks)->toBe('Signed certificate and ID card replaced. Ready for release.');

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.certificate_files.view', [$first, 'id-card']))->assertOk();
    $show()->assertOk()->assertSee('Release Certificate &amp; ID', false);
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.applications.releasing'))
        ->assertOk()->assertSee('Ready for Release');

    // Release: the applicant is told they are ready for pickup; PCT ends.
    Carbon::setTestNow(Carbon::parse('2026-11-04 09:40:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.release', $first))->assertSessionHas('success');

    $accreditation->refresh();
    $step9 = $first->pctEntries()->where('step_number', 9)->first();
    expect($accreditation->released_at)->not->toBeNull()
        ->and($step9->completed_at)->not->toBeNull()
        ->and($step9->elapsed_seconds)->toBe(2400)
        ->and($first->pctEntries()->whereNull('completed_at')->count())->toBe(0);

    $ready = fn ($mail) => $mail->hasTo('practitioner@example.com') && $mail->viewName === 'emails.certificate_ready';
    expect(Mail::queued(\App\Mail\ApplicationNoticeEmail::class, $ready)->isNotEmpty() || Mail::sent(\App\Mail\ApplicationNoticeEmail::class, $ready)->isNotEmpty())->toBeTrue();
    expect((new \App\Mail\ApplicationNoticeEmail($first->fresh(), 'emails.certificate_ready', 'x'))->render())
        ->toContain('Identification Card')->toContain('Accreditation Division')->toContain('1033-261103-1951');

    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.release', $first))->assertSessionHas('error');
    $show()->assertOk()->assertSee('Certificate and ID Card Released');
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.applications.releasing'))
        ->assertOk()->assertViewHas('applications', fn ($apps) => ! $apps->contains('id', $first->id) && $apps->contains('id', $second->id));
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.division.accreditations.active'))
        ->assertOk()->assertSee('1033-261103-1951');
    $this->get(route('track', ['tracking_number' => $first->tracking_number]))
        ->assertOk()->assertSee('Certificate and ID Card Ready for Pickup');

    $summary = app(PctService::class)->getSummary($first->fresh());
    expect($summary['total_target'])->toBe(18.2);
});

test('an applicant who does not pass the interview is archived and may apply again after 6 months', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracBook($this, $application);

    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.start_interview', $application));
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:30:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.stop_interview', $application));

    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.interview_result', $application), ['result' => 'not_passed'])
        ->assertRedirect(route('admin.accreditation.division.applications.archived'));

    $application->refresh();
    expect($application->latestStatus->status->name)->toBe('Rejected')
        ->and($application->archive_reason)->toBe('interview_failed')
        ->and($application->reapply_after->toDateString())->toBe('2027-05-05')
        ->and($application->pctEntries()->whereNull('completed_at')->count())->toBe(0);

    expect(pracMailed(PractitionerApplicationArchivedEmail::class, 'practitioner@example.com'))->toBeTrue();
    expect((new PractitionerApplicationArchivedEmail($application))->render())
        ->toContain('Interview Result: Did Not Pass')->toContain('after 6 months')->toContain('May 5, 2027');

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()->assertSee('Did not pass the panel interview.')->assertSee('May 05, 2027');
    $this->get(route('track', ['tracking_number' => $application->tracking_number]))
        ->assertOk()->assertSee('Interview Result: Did Not Pass');

    Carbon::setTestNow(Carbon::parse('2027-05-04 12:00:00'));
    $this->postJson(route('register.store'), pracPayload())
        ->assertStatus(422)->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'did not pass the panel interview'));

    Carbon::setTestNow(Carbon::parse('2027-05-05 08:00:00'));
    $this->artisan('practitioner:enforce-deadlines')->assertSuccessful();
    expect(Application::find($application->id))->toBeNull();
});

test('a practitioner accreditation number does not move the FATPro sequence', function () {
    $nextFatpro = fn () => (new ReflectionMethod(\App\Http\Controllers\Admin\HCD\ApplicationController::class, 'generateNewAccreditationNumber'))
        ->invoke(app(\App\Http\Controllers\Admin\HCD\ApplicationController::class), '261103');

    $before = $nextFatpro();

    $application = pracSubmit($this);
    \App\Models\Accreditation::create([
        'user_id' => $application->user_id, 'application_id' => $application->id, 'accreditation_type_id' => config('accreditation.types.practitioner'),
        'accreditation_number' => '1033-261103-1951', 'date_of_accreditation' => '2026-11-03', 'validity_date' => '2029-11-03', 'status' => 'active',
    ]);

    expect($nextFatpro())->toBe($before)->not->toEndWith('-1952');
});

test('PCT Step 8 continues Step 6: it starts from the time Step 6 ended with, against 4 days 4 hours', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracBook($this, $application);

    // Step 6 runs from the stop (Thu 10:30) to the signed recommendation (Fri 10:30): 9 working hours.
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.start_interview', $application));
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:30:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.stop_interview', $application));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.interview_result', $application), ['result' => 'passed']);
    Carbon::setTestNow(Carbon::parse('2026-11-06 10:30:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.upload_recommendation', $application), [
        'signed_recommendation_letter' => UploadedFile::fake()->create('signed.pdf', 80, 'application/pdf'),
    ]);
    $this->post(route('track.submit_payment'), [
        'tracking_number'  => $application->tracking_number,
        'proof_of_payment' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
    ]);
    Carbon::setTestNow(Carbon::parse('2026-11-06 11:00:00'));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.evaluate_payment', $application), ['proof_of_payment_status' => 'approved']);

    expect($application->pctEntries()->where('step_number', 6)->first()->elapsed_seconds)->toBe(32400);

    // Two working hours into Step 8: it shows 9 + 2 hours of its 4 days 4 hours.
    Carbon::setTestNow(Carbon::parse('2026-11-06 13:00:00'));
    $summary = app(PctService::class)->getSummary($application->fresh());
    $step8   = collect($summary['steps'])->firstWhere('number', 8);

    expect($step8['elapsed_seconds'])->toBe(32400 + 7200)
        ->and($step8['target_seconds'])->toBe(144000)
        ->and($step8['percent'])->toEqual(28)
        ->and($step8['continues'])->toBe(6);

    // The total counts Step 6's time once: 1.5h interview + 9h + 30m payment + 2h.
    expect($summary['total_elapsed'])->toBe(round((5400 + 32400 + 1800 + 7200) / 32400, 1))
        ->and($summary['total_target'])->toBe(18.2);

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()->assertSee('(continues Step 6)')->assertSee('data-seconds="39600"', false);
});

/** Pass the interview, upload the signed recommendation, and have the applicant pay. */
function pracToPayment($test, Application $application): void
{
    pracBook($test, $application);
    pracAs($test, $test->accVerifier)->post(route('admin.accreditation.practitioner.start_interview', $application));
    pracAs($test, $test->accVerifier)->post(route('admin.accreditation.practitioner.stop_interview', $application));
    pracAs($test, $test->accVerifier)->post(route('admin.accreditation.practitioner.interview_result', $application), ['result' => 'passed']);
    pracAs($test, $test->accVerifier)->post(route('admin.accreditation.practitioner.upload_recommendation', $application), [
        'signed_recommendation_letter' => UploadedFile::fake()->create('signed.pdf', 80, 'application/pdf'),
    ]);
    $test->post(route('track.submit_payment'), [
        'tracking_number'  => $application->tracking_number,
        'proof_of_payment' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'),
    ])->assertSessionHas('success');
}

test('an accredited practitioner gets their own portal: dashboard, profile and renewal; FATPro pages are closed', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracAccredit($this, $application);
    $practitioner = $application->fresh()->user;

    expect($practitioner->usesPractitionerPortal())->toBeTrue();

    $this->flushSession();
    $this->actingAs($practitioner)->get(route('applicant.dashboard'))
        ->assertOk()
        ->assertSee('Practitioner Dashboard')
        ->assertSee('1033-261103-1951')
        ->assertSee('are being prepared')
        ->assertSee('My Profile')
        ->assertSee('href="' . route('applicant.practitioner.renewal') . '"', false)
        ->assertDontSee('Reinstatement')
        ->assertDontSee('data-locked-notice', false)
        ->assertDontSee('Submission report')
        ->assertDontSee('FATPRO Instructor');

    foreach (['applicant.instructors.index', 'applicant.ntc.index', 'applicant.renewal.index'] as $route) {
        $this->actingAs($practitioner)->get(route($route))->assertRedirect(route('applicant.dashboard'));
    }

    $this->actingAs($practitioner)->get(route('profile.index'))->assertOk()->assertDontSee('Submission report');
});

test('Archive Application at the payment stage archives it as FATPro does: Rejected, no waiting period, record kept', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracBook($this, $application);
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.start_interview', $application));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.stop_interview', $application));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.interview_result', $application), ['result' => 'passed']);

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()->assertSee('Archive Application')->assertSee('archivePaymentModal');

    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.archive', $application))
        ->assertRedirect(route('admin.accreditation.division.applications.archived'));

    $application->refresh();
    expect($application->latestStatus->status->name)->toBe('Rejected')
        ->and($application->archive_reason)->toBe('manual')
        ->and($application->reapply_after)->toBeNull()
        ->and($application->pctEntries()->whereNull('completed_at')->count())->toBe(0);
    expect(pracMailed(\App\Mail\ApplicationResultEmail::class, 'practitioner@example.com'))->toBeTrue();

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.division.applications.archived'))
        ->assertOk()->assertSee('Archived by an admin');

    // Kept, and no waiting period.
    Carbon::setTestNow(Carbon::parse('2027-06-01 08:00:00'));
    $this->artisan('practitioner:enforce-deadlines')->assertSuccessful();
    expect(Application::find($application->id))->not->toBeNull()
        ->and(app(\App\Services\PractitionerArchiveService::class)->blockingApplication('practitioner@example.com'))->toBeNull();
});

test('an accreditation can be moved to the archived list and back, as FATPro', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracAccredit($this, $application);

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()->assertSee('Move to Archived');

    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.accreditation.archive', $application))->assertSessionHas('success');
    expect($application->fresh()->accreditation->status)->toBe('archived');
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.division.applications.archived'))
        ->assertOk()->assertViewHas('applications', fn ($apps) => $apps->contains('id', $application->id));
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()->assertSee('Unarchive');

    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.accreditation.unarchive', $application))->assertSessionHas('success');
    expect($application->fresh()->accreditation->status)->toBe('active');
});

test('Update Schedule moves a booked interview to another open slot and tells the applicant', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    $booked = pracBook($this, $application);
    $other  = pracSlot('2026-11-06', '13:00', '14:30', ['mode' => 'online', 'venue' => 'https://meet.example.com/x']);

    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()->assertSee('Update Schedule')
        ->assertSee('name="slot_id" value="' . $other->id . '"', false)
        ->assertDontSee('name="slot_id" value="' . $booked->id . '"', false);

    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.reschedule_interview', $application), ['slot_id' => $other->id])
        ->assertSessionHas('success');

    expect($booked->fresh()->application_id)->toBeNull()
        ->and($other->fresh()->application_id)->toBe($application->id);
    $interview = $application->fresh()->interview;
    expect($interview->interview_date->toDateString())->toBe('2026-11-06')
        ->and($interview->mode)->toBe('online');

    $updated = fn ($mail) => $mail->hasTo('practitioner@example.com') && $mail->isUpdate;
    expect(Mail::queued(InterviewScheduleEmail::class, $updated)->isNotEmpty() || Mail::sent(InterviewScheduleEmail::class, $updated)->isNotEmpty())->toBeTrue();

    // A slot another applicant holds cannot be taken.
    $second = pracSubmit($this, ['email' => 'second@example.com', 'first_name' => 'Pedro']);
    pracApproveAll($this, $second);
    $taken = pracSlot('2026-11-09', '09:00', '10:30', ['application_id' => $second->id]);
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.reschedule_interview', $application), ['slot_id' => $taken->id])
        ->assertSessionHas('error');
    expect($other->fresh()->application_id)->toBe($application->id);

    // Nor can a started interview be moved.
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.start_interview', $application));
    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.reschedule_interview', $application), ['slot_id' => $booked->id])
        ->assertSessionHas('error');
    expect($booked->fresh()->application_id)->toBeNull();
});

test('a rejected proof of payment must be re-uploaded within 5 working days or the application is archived', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracToPayment($this, $application);

    pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.evaluate_payment', $application), [
        'proof_of_payment_status' => 'rejected', 'proof_of_payment_remarks' => 'Blurred',
    ]);

    // Wed 4, Thu 5, Fri 6, Mon 9, Tue 10.
    $application->refresh();
    expect($application->compliance_due_date->toDateString())->toBe('2026-11-10');
    expect((new \App\Mail\ApplicationNoticeEmail($application, 'emails.payment_rejection', 'x', $application->payment))->render())
        ->toContain('5 working days')->toContain('Tuesday, November 10, 2026');
    CacheService::bustTrackingCache($application->tracking_number);
    $this->get(route('track', ['tracking_number' => $application->tracking_number]))
        ->assertOk()->assertSee('Your proof of payment was rejected.')->assertSee('Re-upload Deadline: Tuesday, November 10, 2026');

    // Missed: the daily run archives it.
    Carbon::setTestNow(Carbon::parse('2026-11-11 08:00:00'));
    $this->artisan('practitioner:enforce-deadlines')->assertSuccessful();

    $application->refresh();
    expect($application->latestStatus->status->name)->toBe('Rejected')
        ->and($application->archive_reason)->toBe('payment_non_compliance')
        ->and($application->reapply_after->toDateString())->toBe('2026-12-11');
    expect((new PractitionerApplicationArchivedEmail($application))->render())->toContain('proof of payment');
});

test('a proof of payment re-uploaded in time clears the deadline; one too late is refused and archives', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $onTime = pracSubmit($this);
    $late   = pracSubmit($this, ['email' => 'second@example.com', 'first_name' => 'Pedro']);
    pracToPayment($this, $onTime);
    pracToPayment($this, $late);
    foreach ([$onTime, $late] as $app) {
        pracAs($this, $this->accVerifier)->post(route('admin.accreditation.practitioner.evaluate_payment', $app), ['proof_of_payment_status' => 'rejected']);
    }

    $pay = fn (Application $app) => $this->post(route('track.submit_payment'), [
        'tracking_number'  => $app->tracking_number,
        'proof_of_payment' => UploadedFile::fake()->create('again.pdf', 40, 'application/pdf'),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-11-10 16:00:00'));
    $pay($onTime)->assertSessionHas('success');
    expect($onTime->fresh()->compliance_due_date)->toBeNull()
        ->and($onTime->fresh()->latestStatus->status->name)->toBe('Payment Verification');

    Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00'));
    $pay($late)->assertSessionHas('error');
    expect($late->fresh()->archive_reason)->toBe('payment_non_compliance');

    $this->artisan('practitioner:enforce-deadlines')->assertSuccessful();
    expect($onTime->fresh()->archived_at)->toBeNull();
});

test('accreditation expiry reminders and the expired notice use the practitioner copy of the FATPro format', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracAccredit($this, $application);
    $accreditation = $application->fresh()->accreditation;

    $reminder = new \App\Mail\AccreditationExpiryReminderEmail($accreditation, '1 month');
    expect($reminder->envelope()->subject)->toBe('OSH Practitioner Accreditation Expiring in 1 month — ARMS')
        ->and($reminder->render())->toContain('Occupational Safety and Health (OSH) Practitioner')->toContain('1033-261103-1951')
        ->toContain('Expires in 1 month')->toContain('Accreditation Division');

    $expired = new \App\Mail\AccreditationExpiredEmail($accreditation);
    expect($expired->render())->toContain('has expired as of')->toContain('OSH Practitioner')->toContain('reinstatement');

    // The daily check sends it.
    Carbon::setTestNow(Carbon::parse('2029-10-10 08:00:00'));
    $this->artisan('accreditation:expiry-check')->assertSuccessful();
    expect(pracMailed(\App\Mail\AccreditationExpiryReminderEmail::class, 'practitioner@example.com'))->toBeTrue();
});

/* ── OSH Consultant: the same form and process, its own checklist ─────────── */

/** A complete, valid OSH Consultant registration: the practitioner form with the consultant uploads. */
function consPayload(array $override = []): array
{
    $pdf     = fn (string $name) => UploadedFile::fake()->create($name, 50, 'application/pdf');
    $payload = pracPayload(array_replace_recursive([
        'accreditation_type_id' => config('accreditation.types.consultant'),
        'email'                 => 'consultant@example.com',
    ], $override));

    // CONS_06 (PRC ID) is optional, so it is left out.
    $payload['documents'] = $override['documents'] ?? [
        'CONS_01' => $pdf('contract.pdf'),
        'CONS_02' => $pdf('projects.pdf'),
        'CONS_03' => $pdf('advanced.pdf'),
        'CONS_04' => $pdf('supervisory.pdf'),
        'CONS_05' => $pdf('tor.pdf'),
    ];

    return $payload;
}

function consSubmit($test, array $override = []): Application
{
    $test->post(route('register.store'), consPayload($override))->assertOk()->assertJson(['status' => 'pending']);

    $pending = PendingRegistration::where('email', $override['email'] ?? 'consultant@example.com')->firstOrFail();
    $test->get(route('register.verify', $pending->token))->assertOk();

    return Application::latest('id')->firstOrFail();
}

test('an OSH Consultant applies with the practitioner form and its own documentary requirements', function () {
    // Every consultant upload but the PRC ID is required — and the practitioner ones are not asked for.
    $missing = consPayload();
    unset($missing['documents']['CONS_01']);
    $this->postJson(route('register.store'), $missing)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['documents.CONS_01'])
        ->assertJsonMissingValidationErrors(['documents.CONS_06', 'documents.PRAC_01']);

    $application = consSubmit($this);

    expect($application->isConsultant())->toBeTrue()
        ->and($application->isPractitioner())->toBeTrue() // the practitioner process
        ->and($application->accreditationTitle())->toBe('OSH Consultant')
        ->and($application->paymentAmount())->toBe('850.00')
        ->and($application->latestStatus->status->name)->toBe('Submitted');

    // The generated form (CONS_00) and the five uploads — no practitioner codes.
    $codes = $application->documents->pluck('documentField.code')->sort()->values()->all();
    expect($codes)->toBe(['CONS_00', 'CONS_01', 'CONS_02', 'CONS_03', 'CONS_04', 'CONS_05'])
        ->and($application->documents->firstWhere('documentField.code', 'CONS_00')->userDocument->file_path)
        ->toBe($application->practitionerProfile->application_form_path);

    // "I would like to apply for Accreditation as: [X] OSH Consultant".
    expect(\App\Support\PractitionerForm::fromApplication($application)['applying_as'])->toBe('consultant');

    // Its own Accreditation division list; the practitioner list leaves it out.
    // (The header's notifications name it on every page, so check the lists themselves.)
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.consultant.new.pending'))
        ->assertOk()->assertSee('OSH Consultant — New Applications')
        ->assertViewHas('applications', fn ($apps) => $apps->pluck('id')->all() === [$application->id]);
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.new.pending'))
        ->assertOk()->assertSee('OSH Practitioner — New Applications')
        ->assertViewHas('applications', fn ($apps) => $apps->isEmpty());
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $application))
        ->assertOk()->assertSee('OSH Consultant Requirements')
        ->assertSee(route('admin.accreditation.consultant.new.pending'), false);
});

test('the registration page offers Consultant with its blue photo background and checklist', function () {
    $html = $this->get(route('register'))->assertOk()->getContent();

    expect($html)->toContain('<option value="2">Consultant</option>')
        ->toContain('ID picture with a blue background')
        ->toContain('ID picture with a red background')
        ->toContain('name="documents[CONS_01]"')
        ->toContain('name="documents[CONS_06]"')
        ->toContain('Consultant Documentary Requirements')
        // The form partial brings its own control styles, once; nothing on registration is locked.
        ->toContain('css/practitioner-fields.css')
        ->not->toContain('Not on the renewal form')
        ->not->toContain('<fieldset class="prac-fieldset" disabled');
    expect(substr_count($html, 'css/practitioner-fields.css'))->toBe(1);

    // The PRC ID upload is the consultant's only optional one.
    expect(preg_match('/name="documents\[CONS_06\]"[^>]*required/', $html))->toBe(0)
        ->and(preg_match('/name="documents\[CONS_05\]"[^>]*required/', $html))->toBe(1);

    // The Review Details preview ticks OSH Consultant.
    $preview = $this->postJson(route('register.practitioner.preview'), [
        'accreditation_type_id' => config('accreditation.types.consultant'),
        'first_name' => 'Ana', 'last_name' => 'Reyes',
    ])->assertOk()->json('html');
    expect($preview)->toMatch('#<span class="af-box">X</span>OSH Consultant#')
        ->not->toMatch('#<span class="af-box">X</span>OSH Practitioner#');
});

test('an OSH Consultant goes through the same interview and payment and gets its own numbers from 519', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $consultant   = consSubmit($this);
    $practitioner = pracSubmit($this);
    pracAccredit($this, $consultant);
    pracAccredit($this, $practitioner);

    // Consultant: 1030-MMDDYY-OSHC-NNNN; practitioner: 1033-YYMMDD-NNNN.
    expect($consultant->fresh()->accreditation->accreditation_number)->toBe('1030-110326-OSHC-0519')
        ->and($practitioner->fresh()->accreditation->accreditation_number)->toBe('1033-261103-1951');

    // The consultant certificate wording.
    expect(config('accreditation.certificate_text.2.type'))->toBe('OSH Consultant')
        ->and(config('accreditation.certificate_text.2.body'))->toContain('Occupational Safety and Health Consultant');

    // The OSHC consultant template: front and back.
    $views = [];
    $certData = [];
    \Illuminate\Support\Facades\View::creator('*', function ($view) use (&$views) {
        $views[] = $view->getName();
    });
    \Illuminate\Support\Facades\View::composer('admin.accreditation.practitioner.certificate_consultant_pdf', function ($view) use (&$certData) {
        $certData = $view->getData();
    });
    $pdf = pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.certificate', [
        $consultant, 'executive_director' => 'JOSE MARIA S. BATINO',
        'back_signatory' => 'Maria Beatriz G. Villanueva, MD, MOH, PhD', 'back_signatory_title' => 'OIC-Deputy Executive Director',
    ]))->assertOk()->assertHeader('content-type', 'application/pdf')->getContent();
    expect($views)->toContain('admin.accreditation.practitioner.certificate_consultant_pdf')
        ->not->toContain('admin.accreditation_certificate')->not->toContain('admin.accreditation.practitioner.certificate_pdf')
        ->and(preg_match_all('#/Type ?/Page(?!s)#', $pdf))->toBe(2)
        ->and($certData['backSignatory'])->toBe('Maria Beatriz G. Villanueva, MD, MOH, PhD');

    $render = fn (bool $isRenewal) => view('admin.accreditation.practitioner.certificate_consultant_pdf', array_merge($certData, ['isRenewal' => $isRenewal]))->render();
    $holder = trim(($certData['honorific'] ? $certData['honorific'] . ' ' : '') . $certData['practitionerName']);
    expect($render(false))
        ->toContain('No. 1030-110326-OSHC-0519')->toContain('OCCUPATIONAL SAFETY AND HEALTH')->toContain('CONSULTANT')
        ->toContain('is accredited by this Department as Occupational Safety and Health Consultant')
        ->toContain('Rules 1030 and 1974 of the Occupational Safety and Health Standards')
        ->toContain('<strong><u>OSH Consultant</u></strong>.')->toContain('1x1 ID picture in blue background')
        ->toContain('(Signature Over Printed Name)')->toContain('Date:')
        ->toContain('certifies that <strong>' . e($holder) . '</strong> has satisfactorily complied with all the requirements for accreditation as Occupational Safety and Health Consultant per the Occupational Safety and Health Standards (OSHS)')
        ->toContain('Maria Beatriz G. Villanueva, MD, MOH, PhD')
        ->not->toContain('cert-prac-in-field')->not->toContain('cert-cons-back-line')->not->toContain('prior to this renewal');
    expect($render(true))
        ->toContain('all the requirements for the renewal of accreditation as Occupational Safety and Health Consultant')
        ->toContain('<strong>' . e($holder) . '</strong> has been an active OSH Consultant for more than three (3) years prior to this renewal.')
        ->toContain('cert-cons-back-line');

    // The pop-up asks for the OIC-Deputy Executive Director for consultants too.
    pracAs($this, $this->accVerifier)->get(route('admin.accreditation.practitioner.show', $consultant))
        ->assertOk()->assertSee('name="back_signatory"', false)->assertSee('OIC-Deputy Executive Director Name');
});

test('a name suffix is saved with the profile and printed on the application form and certificate', function () {
    $application = pracSubmit($this, ['middle_name' => 'Santos', 'suffix' => 'Jr.']);
    $profile = $application->user->individualProfile;

    expect($profile->suffix)->toBe('Jr.');

    // AF-PCN-A1: the suffix has its own cell after the middle name; the last
    // name stays as entered, and the signature line carries the whole name.
    $form = \App\Support\PractitionerForm::fromApplication($application->fresh());
    expect($form['name']['last'])->toBe('Dela Cruz')
        ->and($form['name']['first'])->toBe('Juan')
        ->and($form['name']['middle'])->toBe('Santos')
        ->and($form['name']['suffix'])->toBe('Jr.')
        ->and($form['signature'])->toBe('Juan Santos Dela Cruz, Jr.');

    $html = view('practitioner.form.pdf', ['form' => $form])->render();
    expect($html)->toContain('<span class="af-label af-label-strong">Suffix</span>')
        ->toContain('<td class="af-name-suffix"><span class="af-value">Jr.</span></td>');

    // No suffix prints N/A, like any other blank field.
    $plain = pracSubmit($this, ['email' => 'no-suffix@example.com']);
    expect(\App\Support\PractitionerForm::fromApplication($plain->fresh())['name']['suffix'])->toBe('N/A');

    // Too long a suffix is refused.
    $this->postJson(route('register.store'), pracPayload(['email' => 'long-suffix@example.com', 'suffix' => str_repeat('X', 21)]))
        ->assertStatus(422)->assertJsonValidationErrors('suffix');
});

test('region and city are picked from the Philippine list, and the server holds them to it', function () {
    // Registration: two dropdowns the Post Training directory's script fills, loaded once.
    $html = $this->get(route('register'))->assertOk()->getContent();
    expect($html)
        ->toMatch('/<select[^>]*name="region"[^>]*data-ph-address-region="person"/')
        ->toMatch('/<select[^>]*name="city"[^>]*data-ph-address-city="person"[^>]*disabled/')
        ->not->toContain('placeholder="e.g. NCR"');
    expect(substr_count($html, 'js/ph-fields.js'))->toBe(1);

    // A city outside the region, or a region not on the list, is refused.
    $this->postJson(route('register.store'), pracPayload(['email' => 'wrong-city@example.com', 'region' => 'Region VII', 'city' => 'Quezon City']))
        ->assertStatus(422)->assertJsonValidationErrors('city')->assertJsonMissingValidationErrors('region');
    $this->postJson(route('register.store'), pracPayload(['email' => 'wrong-region@example.com', 'region' => 'Metro Manila', 'city' => 'Quezon City']))
        ->assertStatus(422)->assertJsonValidationErrors('region');

    // The tracking page's correction holds them to the same list.
    $service = app(\App\Services\PractitionerApplicationService::class);
    foreach ([[['region' => 'NCR', 'city' => 'Cebu City'], 'person.city'], [['region' => 'Visayas', 'city' => 'Cebu City'], 'person.region']] as [$person, $error]) {
        $request = \Illuminate\Http\Request::create('/', 'POST', ['person' => $person]);
        $rules   = array_intersect_key($service->correctionRules($request), array_flip(['person.region', 'person.city']));
        expect(\Illuminate\Support\Facades\Validator::make($request->all(), $rules)->errors()->keys())->toBe([$error]);
    }

    // Accepted, and the renewal form comes back with the stored pair selected.
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));
    $application = pracSubmit($this, ['region' => 'Region VII', 'city' => 'Cebu City']);
    expect($application->user->individualProfile->only('region', 'city'))->toBe(['region' => 'Region VII', 'city' => 'Cebu City']);

    pracAccredit($this, $application);
    $renewal = pracRenewalPage($this, $application->fresh()->user)->assertOk()->getContent();
    expect($renewal)
        ->toMatch('/name="person\[region\]"\s+data-ph-address-region="person"\s+data-value="Region VII"/')
        ->toMatch('/name="person\[city\]"\s+data-ph-address-city="person"\s+data-value="Cebu City"/');
    expect(substr_count($renewal, 'js/ph-fields.js'))->toBe(1);
});

/** An accredited applicant's renewal page, as that applicant on a fresh session. */
function pracRenewalPage($test, User $user)
{
    $test->flushSession();

    return $test->actingAs($user)->get(route('applicant.practitioner.renewal'));
}

/** What the renewal form posts for Review Details: the person fields and the AF-PCR-A2 sections. */
function pracRenewalInput(array $override = []): array
{
    return array_replace_recursive([
        'person' => [
            'first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz', 'suffix' => 'Jr.',
            'sex' => 'Male', 'region' => 'NCR', 'city' => 'Pasig City', 'address' => '5 Ortigas Ave.',
        ],
        'practitioner' => [
            'profile'    => ['civil_status' => 'Married', 'contact_number' => '09171234567', 'type_of_industry' => 'Construction'],
            'workplace'  => ['hazard_level' => 'Hazardous', 'employees_male' => '20', 'employees_female' => '6', 'region_of_employment' => 'NCR — National Capital Region'],
            'experience' => ['total_osh_experience_years' => '7'],
            'work'       => [['company' => 'New Company Inc.', 'designation' => 'Safety Manager', 'date_from' => '2026-01-05']],
            'trainings'  => [
                ['title' => 'Advanced OSH', 'conducted_by' => 'OSHC', 'no_of_hours' => '40'],
                ['title' => 'Fire Safety', 'conducted_by' => 'BFP', 'no_of_hours' => '8'],
            ],
            'lectures'   => [['topic' => 'Working at Heights', 'conducted_for' => 'New Company Inc.', 'no_of_hours' => '4']],
            'skills'     => [['trade_occupation' => 'Safety Manager', 'field_of_expertise' => 'Construction']],
            'awards'     => [['title' => 'Safety Champion 2026', 'description' => 'Zero lost-time accidents', 'issued_by' => 'New Company Inc.']],
            // A locked step the browser would not send; the AF-PCR-A2 has no place for it anyway.
            'education'  => [['degree' => 'Should Not Print', 'school_name_address' => 'Nowhere']],
        ],
    ], $override);
}

test('the renewal form comes filled in from the last application, with what the AF-PCR-A2 does not ask locked', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this, ['suffix' => 'Jr.']);
    pracAccredit($this, $application);
    $practitioner = $application->fresh()->user;

    $html = pracRenewalPage($this, $practitioner)
        ->assertOk()
        ->assertSee('Renewal of Accreditation')
        ->assertSee('Online submission of renewals is not yet available.')
        // The accreditation being renewed: first issued 11/03/2026, no renewal yet.
        ->assertSee('1033-261103-1951')
        ->assertSee('11/03/2026')
        ->assertSee('11/03/2029')
        ->getContent();

    // Filled in from the application: person, profile, work, trainings, lectures, skills.
    expect($html)
        ->toMatch('/name="person\[first_name\]"[^>]*value="Juan"/')
        ->toMatch('/name="person\[suffix\]"[^>]*value="Jr\."/')
        ->toContain('value="ABC Builders"')
        ->toContain('value="BOSH"')
        ->toContain('value="Safety Officer"');

    // Editable: on the AF-PCR-A2.
    foreach (['person\[last_name\]', 'practitioner\[profile\]\[civil_status\]', 'practitioner\[profile\]\[contact_number\]', 'practitioner\[workplace\]\[geo_code\]'] as $name) {
        expect($html)->not->toMatch('/name="' . $name . '"[^>]*disabled/');
    }

    // Locked: not on the AF-PCR-A2, still shown with the value on file.
    foreach (['person\[birthday\]', 'practitioner\[profile\]\[citizenship\]', 'practitioner\[profile\]\[blood_type\]',
              'practitioner\[profile\]\[tin_no\]', 'practitioner\[profile\]\[sss_gsis_no\]', 'practitioner\[workplace\]\[zip_code\]'] as $name) {
        expect($html)->toMatch('/name="' . $name . '"[^>]*disabled/');
    }
    expect($html)->toMatch('/name="person\[birthday\]"[^>]*value="1990-01-15"/')
        ->toContain('Not on the renewal form');

    // Education (with licenses), examinations, memberships, references and declarations: whole steps locked.
    expect(substr_count($html, '<fieldset class="prac-fieldset" disabled>'))->toBe(5)
        ->and($html)->toContain('value="UP Diliman"')
        ->toContain('value="Ref One"')
        ->not->toContain('Add Another Degree')
        ->not->toContain('Add Another Reference');

    // Awards ask for the AF-PCR-A2's Brief Description.
    expect($html)->toContain('practitioner[awards][__IDX__][description]');

    // The checklist: the red-background picture, items 2 – 5, and 6 – 7 hidden until a change of employer.
    expect($html)
        ->toContain('1. Most Recent 1&quot; x 1&quot; Picture')
        ->toContain('In a RED background')
        ->not->toContain('In a BLUE background')
        ->toContain('name="documents[PCR_02]"')
        ->toContain('name="documents[PCR_03]"')   // no certificate uploaded yet: asked for
        ->toContain('name="documents[PCR_04]"')
        ->toContain('name="documents[PCR_05]"')
        ->toContain('Is there a change of employer or position since your last accreditation?')
        ->toContain('data-renewal-employer-docs hidden')
        ->toMatch('/name="documents\[PCR_06\]"[^>]*disabled/')
        ->toMatch('/name="documents\[PCR_07\]"[^>]*disabled/');

    // Review Details only: it cannot be submitted yet.
    expect($html)->toContain('data-renewal-review')
        ->toMatch('/<button[^>]*disabled>\s*<i[^>]*><\/i> Submit Renewal Application/')
        ->toContain('<form id="practitionerRenewalForm" novalidate>');   // posts nowhere
});

test('Review Details renders the AF-PCR-A2 renewal form from what is in the form', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracAccredit($this, $application);
    $practitioner = $application->fresh()->user;

    Carbon::setTestNow(Carbon::parse('2029-09-15 10:00:00'));
    $this->flushSession();
    $html = $this->actingAs($practitioner)
        ->postJson(route('applicant.practitioner.renewal.preview'), pracRenewalInput())
        ->assertOk()
        ->json('html');

    expect($html)
        ->toContain('AF-PCR-A2')
        ->toContain('Revision Code: 0703-0')
        ->toContain('Page 2 of 2')
        ->toContain('(Renewal)')
        ->toContain('I would like to apply for renewal of my accreditation as:')
        ->toContain('<span class="af-box">X</span>OSH PRACTITIONER')
        ->toContain('<span class="af-box"></span>OSH CONSULTANT')
        // The accreditation box: never renewed yet.
        ->toContain('1033-261103-1951')
        ->toMatch('/Date of last renewal:<\/td>\s*<td[^>]*>N\/A</')
        ->toMatch('/Validity:<\/td>\s*<td[^>]*>11\/03\/2029</')
        // What was typed, with the suffix in its own cell.
        ->toContain('<td class="af-name-suffix"><span class="af-value">Jr.</span></td>')
        ->toContain('5 Ortigas Ave., Pasig City')
        ->toContain('New Company Inc.')
        ->toContain('Safety Manager')
        ->toContain('Present')
        ->toContain('Zero lost-time accidents')
        // Hours added up under the trainings and lectures.
        ->toMatch('/Total<\/td>\s*<td class="af-total-value[^"]*">48</')
        ->toMatch('/Total<\/td>\s*<td class="af-total-value[^"]*">4</')
        // Signed with the full name, dated today.
        ->toContain('Juan Santos Dela Cruz, Jr.')
        ->toContain('09/15/2029')
        // Not on the AF-PCR-A2.
        ->not->toContain('Should Not Print')
        ->not->toContain('Educational Attainment')
        ->not->toContain('Character References');

    // Two pages on the PDF too.
    $form = \App\Support\PractitionerForm::renewalFromInput(
        pracRenewalInput()['person'], pracRenewalInput()['practitioner'], $practitioner->email, $application->fresh()->accreditation
    );
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('practitioner.renewal-form.pdf', ['form' => $form])->setPaper('a4', 'portrait')->output();
    expect(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf))->toBe(2);
});

test('an OSH Consultant renews on the same form, with the blue background and the consultant box ticked', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = consSubmit($this);
    pracAccredit($this, $application);
    $consultant = $application->fresh()->user;

    expect($consultant->usesPractitionerPortal())->toBeTrue();

    pracRenewalPage($this, $consultant)
        ->assertOk()
        ->assertSee('1030-110326-OSHC-0519')
        ->assertSee('OSH Consultant')
        ->assertSee('In a BLUE background')
        ->assertDontSee('In a RED background')
        // The renewal checklist is the same for both types.
        ->assertSee('name="documents[PCR_02]"', false)
        ->assertDontSee('name="documents[CONS_01]"', false);

    $html = $this->actingAs($consultant)
        ->postJson(route('applicant.practitioner.renewal.preview'), pracRenewalInput())
        ->assertOk()
        ->json('html');

    expect($html)->toContain('<span class="af-box">X</span>OSH CONSULTANT')
        ->toContain('<span class="af-box"></span>OSH PRACTITIONER')
        ->toContain('1030-110326-OSHC-0519');
});

test('the last Certificate of Accreditation on file is used for the renewal, with a view link', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracAccredit($this, $application);
    $practitioner  = $application->fresh()->user;
    $accreditation = $application->fresh()->accreditation;

    // None on file yet: item 3 is an upload, and there is nothing to view.
    $this->flushSession();
    $this->actingAs($practitioner)->get(route('applicant.practitioner.renewal.certificate'))->assertNotFound();

    Storage::disk('local')->put('certificates/signed.pdf', '%PDF-1.4 signed certificate');
    $accreditation->update(['scanned_certificate' => 'certificates/signed.pdf']);

    pracRenewalPage($this, $practitioner)
        ->assertOk()
        ->assertSee('Your last certificate is already on file')
        ->assertSee('href="' . route('applicant.practitioner.renewal.certificate') . '"', false)
        ->assertDontSee('name="documents[PCR_03]"', false);

    $this->actingAs($practitioner)->get(route('applicant.practitioner.renewal.certificate'))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="signed.pdf"');
});

test('only an active or expired accreditation can be renewed, and the renewal pages are closed to FATPro accounts', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00'));

    $application = pracSubmit($this);
    pracAccredit($this, $application);
    $practitioner  = $application->fresh()->user;
    $accreditation = $application->fresh()->accreditation;

    $accreditation->update(['status' => 'expired']);
    pracRenewalPage($this, $practitioner)->assertOk()->assertSee('Review Details');

    $accreditation->update(['status' => 'revoked']);
    pracRenewalPage($this, $practitioner)
        ->assertOk()
        ->assertSee('cannot be renewed online')
        ->assertDontSee('Review Details');
    $this->actingAs($practitioner)
        ->postJson(route('applicant.practitioner.renewal.preview'), pracRenewalInput())
        ->assertNotFound();

    // Not a practitioner-portal account.
    $fatpro = User::create([
        'email' => 'fatpro-renewal@example.com', 'password' => 'Password123!', 'profile_type' => 'Organization',
        'role_id' => \App\Models\Role::firstOrCreate(['name' => 'Applicant'])->id, 'email_verified_at' => now(),
    ]);
    foreach ([route('applicant.practitioner.renewal'), route('applicant.practitioner.renewal.certificate')] as $url) {
        $this->flushSession();
        $this->actingAs($fatpro)->get($url)->assertRedirect(route('applicant.dashboard'));
    }
});
