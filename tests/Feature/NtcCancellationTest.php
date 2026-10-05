<?php

use App\Mail\NtcCancelledEmail;
use App\Models\Accreditation;
use App\Models\Instructor;
use App\Models\NtcReport;
use App\Models\NtcTrainingMode;
use App\Models\NtcTrainingType;
use App\Models\PostTrainingDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Mail::fake();
});

/*
|--------------------------------------------------------------------------
| Notice of Cancellation
|--------------------------------------------------------------------------
|
| A FATPro may call off a training they have already filed. It is the only
| submission on the portal that nobody evaluates: the status it writes is
| terminal, and the single obligation is to tell the Training Evaluators who
| were expecting the training.
|
| The window is the Report of Changes window — three working days before the
| first training day — so these also guard that the two agree.
|
| ntcFixture() and ntcEvaluator() come from NtcTrainingDaysTest.
|
*/

/** A live training filed by $applicant, starting $daysOut from today. */
function cancellableNtc(User $applicant, Instructor $instructor, int $daysOut, string $status = 'acknowledged'): NtcReport
{
    $accreditation = Accreditation::where('user_id', $applicant->id)->first();

    $days = [
        1 => [now()->addDays($daysOut)->format('Y-m-d')],
        2 => [now()->addDays($daysOut + 1)->format('Y-m-d')],
    ];

    $ntc = NtcReport::create([
        'accreditation_id'     => $accreditation->id,
        'ntc_training_type_id' => NtcTrainingType::where('code', 'OFA')->value('id'),
        'ntc_training_mode_id' => NtcTrainingMode::first()->id,
        'venue'                => 'OSHC Auditorium',
        'training_start_date'  => $days[1][0],
        'training_end_date'    => $days[2][0],
        'status'               => $status,
        'submitted_at'         => now()->subDays(2),
        'acknowledged_at'      => $status === 'acknowledged' ? now()->subDay() : null,
    ]);

    $ntc->syncTrainingDates($days);
    $ntc->instructors()->sync([$instructor->instructor_person_id]);

    return $ntc->refresh();
}

$reason = 'The client postponed the schedule and no replacement batch could be arranged.';

test('a FATPro can cancel an acknowledged training and the evaluators are told', function () use ($reason) {
    [$applicant, $instructor] = ntcFixture('90');
    $evaluator = ntcEvaluator('90');

    $ntc = cancellableNtc($applicant, $instructor, 20);

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.cancel', $ntc->id), ['cancellation_reason' => $reason])
        ->assertRedirect(route('applicant.ntc.index'))
        ->assertSessionHas('success');

    $ntc->refresh();

    expect($ntc->status)->toBe('cancelled');
    expect($ntc->isCancelled())->toBeTrue();
    expect($ntc->cancelled_at)->not->toBeNull();
    expect($ntc->cancelled_by)->toBe($applicant->id);
    expect($ntc->cancellation_reason)->toBe($reason);

    // The evaluators hear about it, and the reason travels with the message.
    Mail::assertQueued(NtcCancelledEmail::class, function ($mail) use ($evaluator, $ntc) {
        return $mail->hasTo($evaluator->email) && $mail->ntcReport->is($ntc);
    });

    // Subject, sender name and reference in the shape every other admin
    // notice uses, so it files alongside them rather than beside them.
    Mail::assertQueued(NtcCancelledEmail::class, function ($mail) use ($applicant, $ntc) {
        $subject = $mail->envelope()->subject;

        return str_starts_with($subject, '[Admin Notification] Training Cancelled — ')
            && str_contains($subject, $applicant->name)
            && str_contains($subject, $ntc->reference_number);
    });

    // The body carries the reason, and says plainly that nothing is asked of them.
    //
    // render() leaves an output buffer open — true of every mailable in this
    // app, not this one — and PHPUnit calls a test that does so risky. Closing
    // what the render opened keeps the assertion without the warning.
    $level = ob_get_level();
    $html  = (new NtcCancelledEmail($ntc, $applicant->name))->render();

    while (ob_get_level() > $level) {
        ob_end_clean();
    }

    expect($html)->toContain($ntc->cancellation_reason);
    expect($html)->toContain('No action is required');
    expect($html)->toContain($ntc->reference_number);

    // And in the portal as well as the inbox.
    expect($evaluator->notifications()->count())->toBe(1);
    expect($evaluator->notifications()->first()->data['reference_number'])
        ->toBe($ntc->reference_number);
});

test('a training still awaiting evaluation can be cancelled too', function () use ($reason) {
    [$applicant, $instructor] = ntcFixture('91');
    ntcEvaluator('91');

    $ntc = cancellableNtc($applicant, $instructor, 20, 'submitted');

    expect($ntc->canCancel())->toBeTrue();

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.cancel', $ntc->id), ['cancellation_reason' => $reason])
        ->assertSessionHas('success');

    expect($ntc->refresh()->status)->toBe('cancelled');
    Mail::assertQueued(NtcCancelledEmail::class);
});

test('the cancellation window closes three working days before the training', function () use ($reason) {
    [$applicant, $instructor] = ntcFixture('92');

    // Inside the window: the same deadline the Report of Changes uses.
    $open = cancellableNtc($applicant, $instructor, 20);

    expect($open->canCancel())->toBeTrue();
    expect($open->changeWindowDeadlineDate()->toDateString())
        ->toBe($open->reportChangesDeadlineDate()->toDateString());
    expect($open->canCancel())->toBe($open->canSubmitReportChanges());

    // Past it: tomorrow's training cannot be called off through the portal.
    $closed = cancellableNtc($applicant, $instructor, 1);

    expect($closed->canCancel())->toBeFalse();

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.cancel', $closed->id), ['cancellation_reason' => $reason])
        ->assertSessionHasErrors('error');

    expect($closed->refresh()->status)->toBe('acknowledged');
    Mail::assertNothingQueued();
});

test('a cancellation needs a reason worth reading', function () {
    [$applicant, $instructor] = ntcFixture('93');

    $ntc = cancellableNtc($applicant, $instructor, 20);

    foreach (['', '   ', 'no'] as $thin) {
        $this->actingAs($applicant)
            ->post(route('applicant.ntc.cancel', $ntc->id), ['cancellation_reason' => $thin])
            ->assertSessionHasErrors('cancellation_reason');
    }

    expect($ntc->refresh()->status)->toBe('acknowledged');
    Mail::assertNothingQueued();
});

test('a training cannot be cancelled twice', function () use ($reason) {
    [$applicant, $instructor] = ntcFixture('94');
    ntcEvaluator('94');

    $ntc = cancellableNtc($applicant, $instructor, 20);

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.cancel', $ntc->id), ['cancellation_reason' => $reason])
        ->assertSessionHas('success');

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.cancel', $ntc->id), ['cancellation_reason' => 'Changed my mind again.'])
        ->assertSessionHasErrors('error');

    // The first reason stands; the second never touched it.
    expect($ntc->refresh()->cancellation_reason)->toBe($reason);
    Mail::assertQueuedCount(1);
});

test('one FATPro cannot cancel another FATPro training', function () use ($reason) {
    [$owner, $ownerInstructor] = ntcFixture('95');
    [$stranger] = ntcFixture('96');

    $ntc = cancellableNtc($owner, $ownerInstructor, 20);

    $this->actingAs($stranger)
        ->post(route('applicant.ntc.cancel', $ntc->id), ['cancellation_reason' => $reason])
        ->assertForbidden();

    expect($ntc->refresh()->status)->toBe('acknowledged');
    Mail::assertNothingQueued();
});

test('cancelling clears the half-finished post training report nobody will file', function () use ($reason) {
    [$applicant, $instructor] = ntcFixture('97');

    $ntc = cancellableNtc($applicant, $instructor, 20);

    PostTrainingDraft::create([
        'ntc_report_id' => $ntc->id,
        'user_id'       => $applicant->id,
        'payload'       => ['applicant_remarks' => 'started before the training was called off'],
        'saved_at'      => now(),
    ]);

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.cancel', $ntc->id), ['cancellation_reason' => $reason])
        ->assertSessionHas('success');

    expect(PostTrainingDraft::where('ntc_report_id', $ntc->id)->exists())->toBeFalse();
});

test('a cancelled training owes no post training report and offers no changes', function () use ($reason) {
    [$applicant, $instructor] = ntcFixture('98');

    $ntc = cancellableNtc($applicant, $instructor, 20);

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.cancel', $ntc->id), ['cancellation_reason' => $reason])
        ->assertSessionHas('success');

    $ntc->refresh();

    // The amendment routes close with it.
    expect($ntc->canCancel())->toBeFalse();
    expect($ntc->detailsAreEditable())->toBeFalse();

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.report_changes', $ntc->id), [])
        ->assertSessionHasErrors('error');

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Cancelled');
    expect($html)->toContain('Not required &mdash; training cancelled');
});

test('an evaluator cannot acknowledge a training that has been called off', function () use ($reason) {
    [$applicant, $instructor] = ntcFixture('99');
    $evaluator = ntcEvaluator('99');

    $ntc = cancellableNtc($applicant, $instructor, 20, 'submitted');

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.cancel', $ntc->id), ['cancellation_reason' => $reason])
        ->assertSessionHas('success');

    // Finalising an evaluation would otherwise write 'acknowledged' straight
    // back over the cancellation and resurrect the training.
    $this->actingAs($evaluator)
        ->postJson(route('admin.hcd.reports.ntc.finalize_evaluation', $ntc->id), [
            'evaluations' => [['id' => 1, 'status' => 'approved']],
        ])
        ->assertStatus(422);

    expect($ntc->refresh()->status)->toBe('cancelled');

    // The page says why, and offers no verdict to cast.
    $html = $this->actingAs($evaluator)
        ->get(route('admin.hcd.reports.ntc.show', $ntc->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Training Cancelled by the FATPro');
    expect($html)->toContain($reason);
    expect($html)->not->toContain('onclick="setNtcDocStatus(');
});

test('the portal offers the cancel button only while the window is open', function () {
    [$applicant, $instructor] = ntcFixture('89');

    $open   = cancellableNtc($applicant, $instructor, 20);
    $closed = cancellableNtc($applicant, $instructor, 1);

    $html = $this->actingAs($applicant)
        ->get(route('applicant.ntc.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Notice of Cancellation');
    expect($html)->toContain(route('applicant.ntc.cancel', $open->id));
    expect($html)->not->toContain(route('applicant.ntc.cancel', $closed->id));

    // The dialog is there, and it asks for a reason before it will submit.
    expect($html)->toContain('id="ntcCancelModal"');
    expect($html)->toContain('name="cancellation_reason"');
});

/*
|--------------------------------------------------------------------------
| The evaluators' record of cancelled trainings
|--------------------------------------------------------------------------
|
| Nothing is deleted by a cancellation, and the trainings do not simply
| disappear from the admin portal. They move to a list of their own, the way
| Report of Changes submissions already do, and stay fully viewable there.
|
*/

test('a cancelled training moves to its own list and off the working one', function () use ($reason) {
    [$applicant, $instructor] = ntcFixture('88');
    $evaluator = ntcEvaluator('88');

    $live      = cancellableNtc($applicant, $instructor, 30);
    $cancelled = cancellableNtc($applicant, $instructor, 20);

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.cancel', $cancelled->id), ['cancellation_reason' => $reason])
        ->assertSessionHas('success');

    // The rows each list was handed, rather than the rendered page: the admin
    // layout carries a notification bell, and cancelling puts the reference
    // number into it — which would match a "not in the table" assertion.
    $ntcResponse = $this->actingAs($evaluator)
        ->get(route('admin.hcd.reports.ntc.index'))
        ->assertOk();

    $listed = $ntcResponse->viewData('ntcReports')->pluck('id');

    expect($listed)->toContain($live->id);
    expect($listed)->not->toContain($cancelled->id);

    // And the cancelled list holds the other half.
    $cancelledResponse = $this->actingAs($evaluator)
        ->get(route('admin.hcd.reports.cancelled.index'))
        ->assertOk();

    $kept = $cancelledResponse->viewData('ntcReports')->pluck('id');

    expect($kept)->toContain($cancelled->id);
    expect($kept)->not->toContain($live->id);

    $cancelledList = $cancelledResponse->getContent();

    // Carrying the reason, who filed it, and a way back to the full record.
    expect($cancelledList)->toContain($cancelled->reference_number);
    expect($cancelledList)->toContain($reason);
    expect($cancelledList)->toContain(route('admin.hcd.reports.ntc.show', $cancelled->id));

    // Sorted and searched by the same component as every other report table.
    expect($cancelledList)->toContain('id="cancelled_admin_table"');
    expect($cancelledList)->toContain('dynamic-table');
});

test('cancelling destroys nothing — the submission is kept whole', function () use ($reason) {
    [$applicant, $instructor] = ntcFixture('87');

    $ntc = cancellableNtc($applicant, $instructor, 20);

    $document = App\Models\NtcDocument::create([
        'ntc_report_id'        => $ntc->id,
        'ntc_document_type_id' => App\Models\NtcDocumentType::first()->id,
        'file_path'            => 'dummy_files/rtcman.pdf',
        'original_filename'    => 'rtcman.pdf',
        'mime_type'            => 'application/pdf',
        'file_size'            => 2048,
        'status'               => 'approved',
    ]);

    $this->actingAs($applicant)
        ->post(route('applicant.ntc.cancel', $ntc->id), ['cancellation_reason' => $reason])
        ->assertSessionHas('success');

    $ntc->refresh();

    // A cancellation is a status, not a delete: every part of the filing is
    // still on record and still attached to it.
    expect(NtcReport::whereKey($ntc->id)->exists())->toBeTrue();
    expect(App\Models\NtcDocument::whereKey($document->id)->exists())->toBeTrue();
    expect($ntc->documents)->toHaveCount(1);
    expect($ntc->trainingDates)->toHaveCount(2);
    expect($ntc->instructors)->toHaveCount(1);
    expect($ntc->submitted_at)->not->toBeNull();
    expect($ntc->venue)->toBe('OSHC Auditorium');
});

test('only a Training Evaluator may open the cancelled list', function () {
    [$applicant] = ntcFixture('86');

    $this->actingAs($applicant)
        ->get(route('admin.hcd.reports.cancelled.index'))
        ->assertForbidden();
});

test('the Reports menu offers Cancelled Training', function () {
    $evaluator = ntcEvaluator('85');

    $html = $this->actingAs($evaluator)
        ->get(route('admin.hcd.reports.cancelled.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Cancelled Training');
    expect($html)->toContain(route('admin.hcd.reports.cancelled.index'));

    // The Reports group is open and this is the entry highlighted within it.
    expect($html)->toContain(route('admin.hcd.reports.report_changes.index'));
    expect($html)->toContain(route('admin.hcd.reports.post_training.index'));
});
