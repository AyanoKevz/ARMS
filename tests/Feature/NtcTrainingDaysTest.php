<?php

use App\Models\Accreditation;
use App\Models\Application;
use App\Models\AdminProfile;
use App\Models\AdminRole;
use App\Models\Division;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\Instructor;
use App\Models\InstructorCredential;
use App\Models\InstructorPerson;
use App\Models\NtcReport;
use App\Models\NtcTrainingMode;
use App\Models\NtcTrainingType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Mail::fake();
});

/**
 * An accredited FATPro with one instructor who clears every eligibility gate.
 *
 * @return array{0: User, 1: Instructor, 2: Application}
 */
function ntcFixture(string $suffix = '01', array $instructorOverrides = []): array
{
    $applicant = User::forceCreate([
        'email'        => "ntc_days_{$suffix}@example.com",
        'password'     => bcrypt('password'),
        'role_id'      => Role::firstOrCreate(['name' => 'Applicant'])->id,
        'profile_type' => 'Organization',
    ]);

    $typeId = \App\Models\AccreditationType::firstOrCreate(
        ['name' => 'First Aid Training Providers']
    )->id;

    $application = Application::create([
        'user_id'               => $applicant->id,
        'accreditation_type_id' => $typeId,
        'application_type'      => 'new',
        'tracking_number'       => "ARMS-DAYS-{$suffix}",
    ]);

    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id'      => ApplicationStatus::firstOrCreate(['name' => 'Approved'])->id,
    ]);

    Accreditation::create([
        'user_id'               => $applicant->id,
        'application_id'        => $application->id,
        'accreditation_type_id' => $typeId,
        'accreditation_number'  => "FATPRO-DAYS-{$suffix}",
        'date_of_accreditation' => now()->subMonths(2)->format('Y-m-d'),
        'validity_date'         => now()->addYears(2)->format('Y-m-d'),
        'status'                => 'active',
    ]);

    $person = InstructorPerson::create([
        'user_id'    => $applicant->id,
        'first_name' => 'Rosa',
        'last_name'  => "Trainer{$suffix}",
    ]);

    $instructor = Instructor::create(array_merge([
        'user_id'              => $applicant->id,
        'application_id'       => $application->id,
        'instructor_person_id' => $person->id,
        'first_name'           => 'Rosa',
        'last_name'            => "Trainer{$suffix}",
        'cv_path'              => 'dummy_files/cv.pdf',
        'cv_status'            => 'approved',
        'status'               => 'approved',
    ], $instructorOverrides));

    foreach (['EMS', 'TM1', 'NTTC'] as $type) {
        InstructorCredential::create([
            'instructor_id' => $instructor->id,
            'type'          => $type,
            'number'        => "{$type}-{$suffix}",
            'validity_date' => now()->addYear()->format('Y-m-d'),
            'status'        => 'approved',
        ]);
    }

    return [$applicant, $instructor, $application];
}

/** How many NTCs this FATPro has on file. The seeder creates others. */
function ntcCountFor(User $applicant): int
{
    return NtcReport::whereHas(
        'accreditation',
        fn ($q) => $q->where('user_id', $applicant->id)
    )->count();
}

/**
 * A submission payload for one training type.
 *
 * $days is keyed by the day of the course each date belongs to, matching what
 * the form posts. Left out, it fills one consecutive date per day from the
 * earliest date the lead time allows — a valid submission for any type.
 *
 * The start and end dates are derived server-side and are not posted at all.
 */
function ntcPayload(string $code, Instructor $instructor, ?array $days = null): array
{
    $start    = NtcReport::earliestAllowedStartDate();
    $required = NtcReport::durationDaysForCode($code);

    if ($days === null) {
        $days = [];

        for ($dayNo = 1; $dayNo <= $required; $dayNo++) {
            $days[$dayNo] = [$start->copy()->addDays($dayNo - 1)->format('Y-m-d')];
        }
    }

    return [
        'ntc_training_type_id' => NtcTrainingType::where('code', $code)->value('id'),
        'ntc_training_mode_id' => NtcTrainingMode::first()->id,
        'venue'                => 'OSHC Auditorium, Quezon City',
        'training_dates'       => $days,
        'instructor_ids'       => [$instructor->instructor_person_id],
        'file_rtcman'          => UploadedFile::fake()->create('rtcman.pdf', 80, 'application/pdf'),
        'file_prog'            => UploadedFile::fake()->create('prog.pdf', 80, 'application/pdf'),
    ];
}

test('a one-day EFA needs no extra dates and ends on the day it starts', function () {
    [$applicant, $instructor] = ntcFixture('01');

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('EFA', $instructor))
        ->assertSessionHas('success');

    $ntc = NtcReport::latest('id')->first();

    expect($ntc->trainingDates)->toHaveCount(1);
    expect($ntc->training_start_date->toDateString())
        ->toBe($ntc->training_end_date->toDateString());
});

test('training days may fall on a weekend and need not be consecutive', function () {
    [$applicant, $instructor] = ntcFixture('02');

    // Deliberately gapped, and deliberately including a Saturday.
    $start    = NtcReport::earliestAllowedStartDate();
    $saturday = $start->copy()->next(Carbon\Carbon::SATURDAY);
    $days     = [
        1 => [$start->format('Y-m-d')],
        2 => [$saturday->format('Y-m-d')],
        3 => [$saturday->copy()->addDays(9)->format('Y-m-d')],
        4 => [$saturday->copy()->addDays(20)->format('Y-m-d')],
    ];

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('SFA', $instructor, $days))
        ->assertSessionHas('success');

    $ntc = NtcReport::latest('id')->first();

    expect($ntc->trainingDates)->toHaveCount(4);

    // day_no follows the calendar, not the order the dates were typed in.
    expect($ntc->trainingDates->pluck('day_no')->all())->toBe([1, 2, 3, 4]);

    // Start and end are the extremes of the set, which is what every deadline
    // in the system goes on reading.
    expect($ntc->training_start_date->toDateString())->toBe($start->format('Y-m-d'));
    expect($ntc->training_end_date->toDateString())
        ->toBe($saturday->copy()->addDays(20)->format('Y-m-d'));
});

test('the wrong number of training days is rejected', function () {
    [$applicant, $instructor] = ntcFixture('03');

    $start = NtcReport::earliestAllowedStartDate();

    // SFA runs for four days; only two are given.
    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('SFA', $instructor, [
            1 => [$start->format('Y-m-d')],
            2 => [$start->copy()->addDay()->format('Y-m-d')],
        ]))
        ->assertSessionHasErrors('training_dates');

    expect(ntcCountFor($applicant))->toBe(0);
});

test('the same date cannot be counted twice', function () {
    [$applicant, $instructor] = ntcFixture('04');

    $start = NtcReport::earliestAllowedStartDate();

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('OFA', $instructor, [
            1 => [$start->format('Y-m-d')],
            2 => [$start->format('Y-m-d')],
        ]))
        ->assertSessionHasErrors('training_dates');

    expect(ntcCountFor($applicant))->toBe(0);
});

test('every training day must clear the 10-working-day lead time', function () {
    [$applicant, $instructor] = ntcFixture('05');

    // A legal second day cannot drag a first one inside the window.
    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('OFA', $instructor, [
            1 => [now()->addDay()->format('Y-m-d')],
            2 => [NtcReport::earliestAllowedStartDate()->format('Y-m-d')],
        ]))
        ->assertSessionHasErrors('training_dates');

    expect(ntcCountFor($applicant))->toBe(0);
});

test('an NTC records the instructors who will conduct the training', function () {
    [$applicant, $instructor] = ntcFixture('06');

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('EFA', $instructor))
        ->assertSessionHas('success');

    expect(NtcReport::latest('id')->first()->instructors->pluck('id')->all())
        ->toBe([$instructor->instructor_person_id]);
});

test('at least one instructor is required', function () {
    [$applicant, $instructor] = ntcFixture('07');

    $payload = ntcPayload('EFA', $instructor);
    $payload['instructor_ids'] = [];

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), $payload)
        ->assertSessionHasErrors('instructor_ids');

    expect(ntcCountFor($applicant))->toBe(0);
});

test('an instructor whose credentials lapse before the last training day is refused', function () {
    [$applicant, $instructor] = ntcFixture('08');

    $start = NtcReport::earliestAllowedStartDate();
    $extra = $start->copy()->addDays(30);

    // Valid on day one, expired well before the last day.
    $instructor->credentials()->where('type', 'TM1')->update([
        'validity_date' => $start->copy()->addDay()->format('Y-m-d'),
    ]);

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('OFA', $instructor, [
            1 => [$start->format('Y-m-d')],
            2 => [$extra->format('Y-m-d')],
        ]))
        ->assertSessionHasErrors('instructor_ids');

    expect(ntcCountFor($applicant))->toBe(0);
});

test('an instructor belonging to another FATPro cannot be declared', function () {
    [$applicant, $ownInstructor] = ntcFixture('09');
    [, $otherInstructor]         = ntcFixture('10');

    $payload = ntcPayload('EFA', $ownInstructor);
    $payload['instructor_ids'] = [$otherInstructor->instructor_person_id];

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), $payload)
        ->assertSessionHasErrors('instructor_ids');

    expect(ntcCountFor($applicant))->toBe(0);
});

test('an instructor with an unapproved CV is not selectable', function () {
    [$applicant, $instructor] = ntcFixture('11', ['cv_status' => 'pending']);

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('EFA', $instructor))
        ->assertSessionHasErrors('instructor_ids');

    expect(ntcCountFor($applicant))->toBe(0);
});

test('a consecutive run reads as a range and a gapped one is listed in full', function () {
    $ntc = new NtcReport();

    // Unsaved, so drive the label off the fallback rather than the day rows.
    $ntc->setRelation('trainingDates', collect([
        new \App\Models\NtcTrainingDate(['training_date' => '2026-11-02', 'day_no' => 1]),
        new \App\Models\NtcTrainingDate(['training_date' => '2026-11-03', 'day_no' => 2]),
        new \App\Models\NtcTrainingDate(['training_date' => '2026-11-04', 'day_no' => 3]),
    ]));
    expect($ntc->trainingPeriodLabel())->toBe('November 02, 2026 — November 04, 2026');

    $ntc->setRelation('trainingDates', collect([
        new \App\Models\NtcTrainingDate(['training_date' => '2026-11-02', 'day_no' => 1]),
        new \App\Models\NtcTrainingDate(['training_date' => '2026-11-07', 'day_no' => 2]),
    ]));
    expect($ntc->trainingPeriodLabel())->toBe('Nov 02, 2026 · Nov 07, 2026');
});

test('the NTC form offers the roster instead of a derived end date', function () {
    [$applicant] = ntcFixture('12');

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-training-days');
    expect($html)->toContain('data-instructor-picker');
    expect($html)->toContain('name="instructor_ids[]"');
    expect($html)->not->toContain('skipping weekends');
});

test('the table lists instructors in their own column, behind a dialog', function () {
    [$applicant, $instructor] = ntcFixture('13');

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('EFA', $instructor))
        ->assertSessionHas('success');

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    // A column of its own, not a second line under the training period.
    expect($html)->toContain('ntc-col-instructors');
    expect($html)->toContain('btn-view-instructors');
    expect($html)->toContain('id="ntcInstructorsModal"');
    expect($html)->not->toContain('<span class="fw-semibold text-secondary">Instructors:</span>');

    // The names travel on the button as JSON for the dialog to read.
    expect($html)->toContain('data-instructors=');
    expect($html)->toContain('Trainer13');
});

test('the picker shows only the name when an instructor is selectable', function () {
    [$applicant] = ntcFixture('14');

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('Credentials valid until');
    expect($html)->not->toContain('Active — credentials approved');
});

test('an instructor who cannot be picked still says why', function () {
    [$applicant] = ntcFixture('15', ['cv_status' => 'pending']);

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('CV has not been approved.');
});

test('the start and end dates are derived from the days, not taken from the form', function () {
    [$applicant, $instructor] = ntcFixture('16');

    $start = NtcReport::earliestAllowedStartDate();
    $last  = $start->copy()->addDays(12);

    $payload = ntcPayload('OFA', $instructor, [
        1 => [$start->format('Y-m-d')],
        2 => [$last->format('Y-m-d')],
    ]);

    // The form posts neither date; a crafted one must not be believed.
    $payload['training_start_date'] = $start->copy()->addDays(99)->format('Y-m-d');
    $payload['training_end_date']   = $start->copy()->addDays(99)->format('Y-m-d');

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), $payload)
        ->assertSessionHas('success');

    $ntc = NtcReport::latest('id')->first();

    expect($ntc->training_start_date->toDateString())->toBe($start->format('Y-m-d'));
    expect($ntc->training_end_date->toDateString())->toBe($last->format('Y-m-d'));
});

test('the day pickers can only run forwards', function () {
    [$applicant] = ntcFixture('17');

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    // The rows are built by ntc.js, so what the page must carry is the floor
    // it chains from and the container the script binds to.
    expect($html)->toContain('data-min-date="' . NtcReport::earliestAllowedStartDate()->format('Y-m-d') . '"');
    expect($html)->toContain('data-day-groups');
});

test('the day picker is locked until a training type is chosen', function () {
    [$applicant] = ntcFixture('18');

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    // The groups are hidden behind a notice until a type says how many to build.
    expect($html)->toContain('data-days-locked');
    expect($html)->toContain('Select a type of training above to choose the training days.');

    // Both dates are outputs: read-only, and posted by neither form.
    expect($html)->toContain('id="training_start_date"');
    expect($html)->toContain('The first of the training dates you select.');
    expect($html)->not->toContain('name="training_start_date"');

    // Both the new-NTC form and the Report of Changes dialog carry the picker.
    expect(substr_count($html, 'data-days-locked'))->toBe(2);
});

test('one day of the course may run over several dates', function () {
    [$applicant, $instructor] = ntcFixture('19');

    $start = NtcReport::earliestAllowedStartDate();

    // An 8-hour Day 1 split across two mornings, then Day 2 on its own.
    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('OFA', $instructor, [
            1 => [$start->format('Y-m-d'), $start->copy()->addDay()->format('Y-m-d')],
            2 => [$start->copy()->addDays(5)->format('Y-m-d')],
        ]))
        ->assertSessionHas('success');

    $ntc = NtcReport::latest('id')->first();

    // Three dates across two days of the course.
    expect($ntc->trainingDates)->toHaveCount(3);
    expect($ntc->trainingDates->pluck('day_no')->all())->toBe([1, 1, 2]);

    expect($ntc->trainingDatesByDay())->toBe([
        1 => [$start->format('Y-m-d'), $start->copy()->addDay()->format('Y-m-d')],
        2 => [$start->copy()->addDays(5)->format('Y-m-d')],
    ]);

    // Start and end still span the whole thing, so the deadlines are unaffected.
    expect($ntc->training_start_date->toDateString())->toBe($start->format('Y-m-d'));
    expect($ntc->training_end_date->toDateString())
        ->toBe($start->copy()->addDays(5)->format('Y-m-d'));
});

test('a day of the course with no date at all is rejected', function () {
    [$applicant, $instructor] = ntcFixture('20');

    $start = NtcReport::earliestAllowedStartDate();

    // Day 1 gets two dates but Day 2 is never filled in.
    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('OFA', $instructor, [
            1 => [$start->format('Y-m-d'), $start->copy()->addDay()->format('Y-m-d')],
        ]))
        ->assertSessionHasErrors('training_dates');

    expect(ntcCountFor($applicant))->toBe(0);
});

test('a later day cannot begin before an earlier one has finished', function () {
    [$applicant, $instructor] = ntcFixture('21');

    $start = NtcReport::earliestAllowedStartDate();

    // Day 1 runs to +10, so Day 2 landing on +3 interleaves the two.
    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('OFA', $instructor, [
            1 => [$start->format('Y-m-d'), $start->copy()->addDays(10)->format('Y-m-d')],
            2 => [$start->copy()->addDays(3)->format('Y-m-d')],
        ]))
        ->assertSessionHasErrors('training_dates');

    expect(ntcCountFor($applicant))->toBe(0);
});

test('a date cannot be filed against a day the training does not have', function () {
    [$applicant, $instructor] = ntcFixture('22');

    $start = NtcReport::earliestAllowedStartDate();

    // EFA runs for one day; there is no Day 2 to put anything under.
    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('EFA', $instructor, [
            1 => [$start->format('Y-m-d')],
            2 => [$start->copy()->addDay()->format('Y-m-d')],
        ]))
        ->assertSessionHasErrors('training_dates');

    expect(ntcCountFor($applicant))->toBe(0);
});

test('even a one-day course can be spread over two dates', function () {
    [$applicant, $instructor] = ntcFixture('23');

    $start = NtcReport::earliestAllowedStartDate();

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('EFA', $instructor, [
            1 => [$start->format('Y-m-d'), $start->copy()->addDays(2)->format('Y-m-d')],
        ]))
        ->assertSessionHas('success');

    $ntc = NtcReport::latest('id')->first();

    expect($ntc->trainingDates)->toHaveCount(2);
    expect($ntc->trainingDates->pluck('day_no')->unique()->all())->toBe([1]);
});

test('a declared instructor survives a renewal that re-files the roster', function () {
    [$applicant, $instructor, $application] = ntcFixture('24');

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('EFA', $instructor))
        ->assertSessionHas('success');

    $ntc      = NtcReport::latest('id')->first();
    $declared = $ntc->instructors->pluck('id')->all();

    expect($declared)->toBe([$instructor->instructor_person_id]);

    // A renewal files every instructor afresh against a new application and the
    // accreditation moves with it. Before instructor_people existed, this is
    // exactly where the NTC lost track of who it had declared.
    $renewal = Application::create([
        'user_id'               => $applicant->id,
        'accreditation_type_id' => $application->accreditation_type_id,
        'application_type'      => 'renewal',
        'tracking_number'       => 'ARMS-RENEW-24',
    ]);

    $refiled = $instructor->replicate();
    $refiled->application_id = $renewal->id;
    $refiled->save();

    foreach ($instructor->credentials as $credential) {
        $copy = $credential->replicate();
        $copy->instructor_id = $refiled->id;
        $copy->save();
    }

    Accreditation::where('user_id', $applicant->id)->update(['application_id' => $renewal->id]);

    // The submission has a new id; the person does not.
    expect($refiled->id)->not->toBe($instructor->id);
    expect($refiled->instructor_person_id)->toBe($instructor->instructor_person_id);

    $ntc->refresh()->load('instructors');
    expect($ntc->instructors->pluck('id')->all())->toBe($declared);

    // Two rows per human in the table, still one entry each on the roster.
    expect(Instructor::where('user_id', $applicant->id)->count())->toBe(2);

    $roster = Instructor::accreditedRosterFor($applicant->id);
    expect($roster)->toHaveCount(1);

    // The point of the whole exercise: what the NTC declared is still pickable,
    // so the Report of Changes dialog opens with it already ticked.
    expect($roster->pluck('instructor_person_id')->all())->toBe($declared);
});

test('the training period opens in a dialog, day by day', function () {
    [$applicant, $instructor] = ntcFixture('25');

    $start = NtcReport::earliestAllowedStartDate();

    // Day 1 over two dates, so the dialog has something a cell could not hold.
    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('OFA', $instructor, [
            1 => [$start->format('Y-m-d'), $start->copy()->addDay()->format('Y-m-d')],
            2 => [$start->copy()->addDays(6)->format('Y-m-d')],
        ]))
        ->assertSessionHas('success');

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('ntc-col-period');
    expect($html)->toContain('btn-view-training-days');
    expect($html)->toContain('id="ntcTrainingDaysModal"');

    // The dates travel on the button, grouped by day, for the dialog to read.
    expect($html)->toContain('data-days=');
    expect($html)->toContain($start->format('F d, Y'));

    // The cell itself no longer spells the period out.
    expect($html)->not->toContain('ntc-date-part');
});

/* ══════════════════════════════════════════════════════════════
   Training Evaluator's correction of an acknowledged NTC

   The FATPro's Report of Changes closes three working days before
   the training; this is what an evaluator has after that.
   ══════════════════════════════════════════════════════════════ */

/** A Training Evaluator who can act on the admin NTC page. */
function ntcEvaluator(string $suffix): User
{
    $evaluator = User::forceCreate([
        'email'        => "ntc_evaluator_{$suffix}@example.com",
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

    return $evaluator;
}

/** An acknowledged NTC whose training is still ahead of us. */
function acknowledgedNtcFor(User $applicant, Instructor $instructor, array $days): NtcReport
{
    $accreditation = Accreditation::where('user_id', $applicant->id)->first();

    $ntc = NtcReport::create([
        'accreditation_id'     => $accreditation->id,
        'ntc_training_type_id' => NtcTrainingType::where('code', 'OFA')->value('id'),
        'ntc_training_mode_id' => NtcTrainingMode::first()->id,
        'venue'                => 'Original Venue',
        'training_start_date'  => $days[1][0],
        'training_end_date'    => end($days)[0],
        'status'               => 'acknowledged',
        'acknowledged_at'      => now()->subDay(),
    ]);

    $ntc->syncTrainingDates($days);
    $ntc->instructors()->sync([$instructor->instructor_person_id]);

    return $ntc->refresh();
}

test('an evaluator can correct an acknowledged NTC before it starts', function () {
    [$applicant, $instructor] = ntcFixture('30');
    $evaluator = ntcEvaluator('30');

    $ntc = acknowledgedNtcFor($applicant, $instructor, [
        1 => [now()->addDays(20)->format('Y-m-d')],
        2 => [now()->addDays(21)->format('Y-m-d')],
    ]);

    $moved = [
        1 => [now()->addDays(30)->format('Y-m-d'), now()->addDays(31)->format('Y-m-d')],
        2 => [now()->addDays(40)->format('Y-m-d')],
    ];

    $this->actingAs($evaluator)
        ->post(route('admin.hcd.reports.ntc.details.update', $ntc->id), [
            'ntc_training_type_id' => $ntc->ntc_training_type_id,
            'ntc_training_mode_id' => $ntc->ntc_training_mode_id,
            'venue'                => 'Corrected Venue, Quezon City',
            'training_dates'       => $moved,
            'instructor_ids'       => [$instructor->instructor_person_id],
            'remarks'              => 'Venue and dates corrected on the FATPro\'s behalf.',
        ])
        ->assertSessionHas('success');

    $ntc->refresh()->load('trainingDates');

    expect($ntc->venue)->toBe('Corrected Venue, Quezon City');
    expect($ntc->trainingDatesByDay())->toBe($moved);

    // The derived columns move with it, so every deadline follows.
    expect($ntc->training_start_date->toDateString())->toBe(now()->addDays(30)->format('Y-m-d'));
    expect($ntc->training_end_date->toDateString())->toBe(now()->addDays(40)->format('Y-m-d'));
});

test('an evaluator cannot correct an NTC once its training has started', function () {
    [$applicant, $instructor] = ntcFixture('31');
    $evaluator = ntcEvaluator('31');

    $ntc = acknowledgedNtcFor($applicant, $instructor, [
        1 => [now()->addDays(20)->format('Y-m-d')],
        2 => [now()->addDays(21)->format('Y-m-d')],
    ]);

    // Today is now the first training day, so the details are settled.
    $ntc->forceFill([
        'training_start_date' => now()->format('Y-m-d'),
        'training_end_date'   => now()->addDay()->format('Y-m-d'),
    ])->save();

    expect($ntc->fresh()->detailsAreEditable())->toBeFalse();

    $this->actingAs($evaluator)
        ->post(route('admin.hcd.reports.ntc.details.update', $ntc->id), [
            'ntc_training_type_id' => $ntc->ntc_training_type_id,
            'ntc_training_mode_id' => $ntc->ntc_training_mode_id,
            'venue'                => 'Too Late Venue',
            'training_dates'       => [
                1 => [now()->addDays(30)->format('Y-m-d')],
                2 => [now()->addDays(31)->format('Y-m-d')],
            ],
            'instructor_ids'       => [$instructor->instructor_person_id],
        ])
        ->assertSessionHasErrors('error');

    expect($ntc->fresh()->venue)->toBe('Original Venue');
});

test('an NTC that is not acknowledged cannot be corrected', function () {
    [$applicant, $instructor] = ntcFixture('32');
    $evaluator = ntcEvaluator('32');

    $ntc = acknowledgedNtcFor($applicant, $instructor, [
        1 => [now()->addDays(20)->format('Y-m-d')],
        2 => [now()->addDays(21)->format('Y-m-d')],
    ]);

    $ntc->update(['status' => 'submitted']);

    expect($ntc->fresh()->detailsAreEditable())->toBeFalse();

    $this->actingAs($evaluator)
        ->post(route('admin.hcd.reports.ntc.details.update', $ntc->id), [
            'ntc_training_type_id' => $ntc->ntc_training_type_id,
            'ntc_training_mode_id' => $ntc->ntc_training_mode_id,
            'venue'                => 'Premature Venue',
            'training_dates'       => [
                1 => [now()->addDays(30)->format('Y-m-d')],
                2 => [now()->addDays(31)->format('Y-m-d')],
            ],
            'instructor_ids'       => [$instructor->instructor_person_id],
        ])
        ->assertSessionHasErrors('error');

    expect($ntc->fresh()->venue)->toBe('Original Venue');
});

test('a correction cannot move a training into the past', function () {
    [$applicant, $instructor] = ntcFixture('33');
    $evaluator = ntcEvaluator('33');

    $ntc = acknowledgedNtcFor($applicant, $instructor, [
        1 => [now()->addDays(20)->format('Y-m-d')],
        2 => [now()->addDays(21)->format('Y-m-d')],
    ]);

    $this->actingAs($evaluator)
        ->post(route('admin.hcd.reports.ntc.details.update', $ntc->id), [
            'ntc_training_type_id' => $ntc->ntc_training_type_id,
            'ntc_training_mode_id' => $ntc->ntc_training_mode_id,
            'venue'                => 'Backdated Venue',
            'training_dates'       => [
                1 => [now()->subDays(5)->format('Y-m-d')],
                2 => [now()->addDays(21)->format('Y-m-d')],
            ],
            'instructor_ids'       => [$instructor->instructor_person_id],
        ])
        ->assertSessionHasErrors('training_dates');

    expect($ntc->fresh()->venue)->toBe('Original Venue');
});

test('the ten-working-day lead time does not apply to a correction', function () {
    [$applicant, $instructor] = ntcFixture('34');
    $evaluator = ntcEvaluator('34');

    $ntc = acknowledgedNtcFor($applicant, $instructor, [
        1 => [now()->addDays(20)->format('Y-m-d')],
        2 => [now()->addDays(21)->format('Y-m-d')],
    ]);

    // Well inside the window a FATPro would have to respect, which is the
    // point: the rule governs filing, not correcting something already filed.
    $soon = now()->addDays(2)->format('Y-m-d');

    $this->actingAs($evaluator)
        ->post(route('admin.hcd.reports.ntc.details.update', $ntc->id), [
            'ntc_training_type_id' => $ntc->ntc_training_type_id,
            'ntc_training_mode_id' => $ntc->ntc_training_mode_id,
            'venue'                => 'Brought Forward',
            'training_dates'       => [
                1 => [$soon],
                2 => [now()->addDays(3)->format('Y-m-d')],
            ],
            'instructor_ids'       => [$instructor->instructor_person_id],
        ])
        ->assertSessionHas('success');

    expect($ntc->fresh()->training_start_date->toDateString())->toBe($soon);
});

test('only a Training Evaluator may correct the details', function () {
    [$applicant, $instructor] = ntcFixture('35');

    $ntc = acknowledgedNtcFor($applicant, $instructor, [
        1 => [now()->addDays(20)->format('Y-m-d')],
        2 => [now()->addDays(21)->format('Y-m-d')],
    ]);

    // The FATPro owns the submission but has no business on the admin route.
    $this->actingAs($applicant)
        ->post(route('admin.hcd.reports.ntc.details.update', $ntc->id), [
            'ntc_training_type_id' => $ntc->ntc_training_type_id,
            'ntc_training_mode_id' => $ntc->ntc_training_mode_id,
            'venue'                => 'Self Served',
            'training_dates'       => [
                1 => [now()->addDays(30)->format('Y-m-d')],
                2 => [now()->addDays(31)->format('Y-m-d')],
            ],
            'instructor_ids'       => [$instructor->instructor_person_id],
        ])
        ->assertForbidden();

    expect($ntc->fresh()->venue)->toBe('Original Venue');
});

test('the evaluator page offers the editor only while it can be used', function () {
    [$applicant, $instructor] = ntcFixture('36');
    $evaluator = ntcEvaluator('36');

    $ntc = acknowledgedNtcFor($applicant, $instructor, [
        1 => [now()->addDays(20)->format('Y-m-d')],
        2 => [now()->addDays(21)->format('Y-m-d')],
    ]);

    $html = $this->actingAs($evaluator)
        ->get(route('admin.hcd.reports.ntc.show', $ntc->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('ntcDetailsEditModal');
    expect($html)->toContain('ntc-training-picker.js');
    expect($html)->toContain('data-prefix="admin_"');

    // Once the training is under way the button and dialog go entirely.
    $ntc->forceFill(['training_start_date' => now()->format('Y-m-d')])->save();

    $later = $this->actingAs($evaluator)
        ->get(route('admin.hcd.reports.ntc.show', $ntc->id))
        ->assertOk()
        ->getContent();

    expect($later)->not->toContain('ntcDetailsEditModal');
});

test('the NTC documents open in a dialog, with the re-upload form inside', function () {
    [$applicant, $instructor] = ntcFixture('40');

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.store'), ntcPayload('EFA', $instructor))
        ->assertSessionHas('success');

    $ntc = NtcReport::latest('id')->first();

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    // The cell is a button; the documents live in a dialog of their own.
    expect($html)->toContain('ntcDocsModal-' . $ntc->id);
    expect($html)->toContain('data-bs-target="#ntcDocsModal-' . $ntc->id . '"');

    // Nothing declined yet, so no re-upload form and no danger styling.
    expect($html)->not->toContain('ntc-reupload-form');

    // Decline one document the way an evaluator does: reject it and wipe the file.
    $doc = $ntc->documents->first();
    $doc->update(['status' => 'rejected', 'file_path' => null, 'remarks' => 'Wrong form.']);

    $declined = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    // The button now calls for action rather than merely offering a look.
    expect($declined)->toContain('Re-upload');
    expect($declined)->toContain('btn-outline-danger');

    // And the form, its drop zone and the remarks are all inside the dialog.
    expect($declined)->toContain('ntc-reupload-form');
    expect($declined)->toContain('dropZoneReject-' . $doc->id);
    expect($declined)->toContain('Wrong form.');
});

test('the FATPro is emailed when an evaluator corrects their NTC', function () {
    [$applicant, $instructor] = ntcFixture('41');
    $evaluator = ntcEvaluator('41');

    $ntc = acknowledgedNtcFor($applicant, $instructor, [
        1 => [now()->addDays(20)->format('Y-m-d')],
        2 => [now()->addDays(21)->format('Y-m-d')],
    ]);

    Mail::fake();

    $this->actingAs($evaluator)
        ->post(route('admin.hcd.reports.ntc.details.update', $ntc->id), [
            'ntc_training_type_id' => $ntc->ntc_training_type_id,
            'ntc_training_mode_id' => $ntc->ntc_training_mode_id,
            'venue'                => 'Corrected Venue',
            'training_dates'       => [
                1 => [now()->addDays(30)->format('Y-m-d')],
                2 => [now()->addDays(31)->format('Y-m-d')],
            ],
            'instructor_ids'       => [$instructor->instructor_person_id],
            'remarks'              => 'Venue was mistyped on filing.',
        ])
        ->assertSessionHas('success');

    // The mailable implements ShouldQueue, so it is queued rather than sent.
    Mail::assertQueued(\App\Mail\NtcDetailsUpdatedEmail::class, function ($mail) use ($applicant) {
        // Addressed to the FATPro whose submission it was.
        if (!$mail->hasTo($applicant->email)) {
            return false;
        }

        // And it says what actually moved, not merely that something did.
        return array_key_exists('Venue', $mail->changes)
            && array_key_exists('Training Days', $mail->changes)
            && $mail->changes['Venue']['from'] === 'Original Venue'
            && $mail->changes['Venue']['to'] === 'Corrected Venue'
            && $mail->note === 'Venue was mistyped on filing.';
    });
});

test('re-saving an NTC without changing anything emails nobody', function () {
    [$applicant, $instructor] = ntcFixture('42');
    $evaluator = ntcEvaluator('42');

    $days = [
        1 => [now()->addDays(20)->format('Y-m-d')],
        2 => [now()->addDays(21)->format('Y-m-d')],
    ];

    $ntc = acknowledgedNtcFor($applicant, $instructor, $days);

    Mail::fake();

    $this->actingAs($evaluator)
        ->post(route('admin.hcd.reports.ntc.details.update', $ntc->id), [
            'ntc_training_type_id' => $ntc->ntc_training_type_id,
            'ntc_training_mode_id' => $ntc->ntc_training_mode_id,
            'venue'                => $ntc->venue,
            'training_dates'       => $days,
            'instructor_ids'       => [$instructor->instructor_person_id],
        ])
        ->assertSessionHas('success');

    Mail::assertNothingQueued();
});
