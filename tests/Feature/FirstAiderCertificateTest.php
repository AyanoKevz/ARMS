<?php

use App\Mail\FirstAiderCertificateEmail;
use App\Models\Accreditation;
use App\Models\AuditLog;
use App\Models\FirstAiderCertificateSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Mail::fake();
    Storage::fake('local');
});

/** The seeded accredited FATPro, their active accreditation and its assigned evaluator. */
function faFixture(): array
{
    $fatpro        = User::where('email', 'kevin25.cloudspace@gmail.com')->firstOrFail();
    $accreditation = Accreditation::where('user_id', $fatpro->id)->where('status', 'active')->firstOrFail();
    $evaluator     = User::findOrFail($accreditation->application->assigned_evaluator_id);

    return [$fatpro, $accreditation, $evaluator];
}

function faSetValidity(Accreditation $accreditation, string $date): void
{
    $accreditation->firstAiderDocuments()['date']->userDocument->update(['value' => $date]);
}

function faSubmit(User $fatpro, string $validity = null)
{
    return test()->actingAs($fatpro)->post(route('applicant.first_aider_certificate.store'), [
        'certificate'   => UploadedFile::fake()->create('renewed.pdf', 120, 'application/pdf'),
        'validity_date' => $validity ?? now()->addYears(2)->toDateString(),
    ]);
}

test('a FATPro renews the certificate and the assigned evaluator is told', function () {
    [$fatpro, $accreditation, $evaluator] = faFixture();
    $before = $accreditation->firstAiderCertificateValidity()->toDateString();

    faSubmit($fatpro)->assertRedirect(route('applicant.dashboard'))->assertSessionHas('success');

    $submission = FirstAiderCertificateSubmission::firstOrFail();
    expect($submission->status)->toBe('pending');
    expect($submission->validity_date->toDateString())->toBe(now()->addYears(2)->toDateString());
    Storage::disk('local')->assertExists($submission->file_path);

    // The certificate on file is untouched until it is approved.
    expect((new Accreditation)->newQuery()->find($accreditation->id)->firstAiderCertificateValidity()->toDateString())->toBe($before);

    Mail::assertQueued(FirstAiderCertificateEmail::class, fn ($m) => $m->kind === 'submitted' && $m->hasTo($evaluator->email));
    expect($evaluator->notifications()->where('type', 'App\Notifications\FirstAiderCertificateSubmittedNotification')->exists())->toBeTrue();
});

test('a renewal needs a PDF and a future validity date, and only one may wait at a time', function () {
    [$fatpro] = faFixture();

    faSubmit($fatpro, now()->subDay()->toDateString())->assertSessionHasErrors('validity_date');

    $this->actingAs($fatpro)->post(route('applicant.first_aider_certificate.store'), [
        'certificate'   => UploadedFile::fake()->image('photo.jpg'),
        'validity_date' => now()->addYear()->toDateString(),
    ])->assertSessionHasErrors('certificate');

    faSubmit($fatpro)->assertSessionHas('success');
    faSubmit($fatpro)->assertSessionHas('error');

    expect(FirstAiderCertificateSubmission::count())->toBe(1);
});

test('approving a renewal makes it the certificate on file and restarts the reminders', function () {
    [$fatpro, $accreditation, $evaluator] = faFixture();
    $accreditation->update(['fa_cert_reminder_3mo_sent_at' => now(), 'fa_cert_expired_notified_at' => now()]);

    faSubmit($fatpro, '2029-05-31');
    $submission = FirstAiderCertificateSubmission::firstOrFail();

    $this->actingAs($evaluator)
        ->post(route('admin.hcd.first_aider_certificates.approve', $submission->id))
        ->assertSessionHas('success');

    $accreditation = Accreditation::findOrFail($accreditation->id);
    $docs = $accreditation->firstAiderDocuments();

    expect($submission->refresh()->status)->toBe('approved');
    expect($accreditation->firstAiderCertificateValidity()->toDateString())->toBe('2029-05-31');
    expect($docs['file']->userDocument->file_path)->toBe($submission->file_path);
    expect($docs['file']->status)->toBe('approved');
    expect($accreditation->fa_cert_reminder_3mo_sent_at)->toBeNull();
    expect($accreditation->fa_cert_expired_notified_at)->toBeNull();

    Mail::assertQueued(FirstAiderCertificateEmail::class, fn ($m) => $m->kind === 'approved' && $m->hasTo($fatpro->email));
    expect(AuditLog::where('action', 'fa_cert.approved')->where('user_id', $evaluator->id)->exists())->toBeTrue();
});

test('rejecting a renewal keeps the old certificate and lets the FATPro try again', function () {
    [$fatpro, $accreditation, $evaluator] = faFixture();
    $before = $accreditation->firstAiderCertificateValidity()->toDateString();

    faSubmit($fatpro);
    $submission = FirstAiderCertificateSubmission::firstOrFail();
    $path = $submission->file_path;

    $this->actingAs($evaluator)
        ->post(route('admin.hcd.first_aider_certificates.reject', $submission->id), ['remarks' => 'Not legible.'])
        ->assertSessionHas('success');

    $submission->refresh();
    expect($submission->status)->toBe('rejected');
    expect($submission->remarks)->toBe('Not legible.');
    Storage::disk('local')->assertMissing($path);
    expect(Accreditation::findOrFail($accreditation->id)->firstAiderCertificateValidity()->toDateString())->toBe($before);

    Mail::assertQueued(FirstAiderCertificateEmail::class, fn ($m) => $m->kind === 'rejected' && $m->hasTo($fatpro->email));

    // The dashboard shows the remarks and offers the upload again.
    $this->flushSession();
    $this->actingAs($fatpro)->get(route('applicant.dashboard'))
        ->assertOk()
        ->assertSee('Not legible.')
        ->assertSee('Update Certificate');

    faSubmit($fatpro)->assertSessionHas('success');
    expect(FirstAiderCertificateSubmission::where('status', 'pending')->count())->toBe(1);
});

test('only the assigned evaluator decides; the Team Lead and other evaluators cannot', function () {
    [$fatpro, $accreditation, $evaluator] = faFixture();
    faSubmit($fatpro);
    $submission = FirstAiderCertificateSubmission::firstOrFail();

    // A second Evaluator, not assigned to this application.
    $otherEvaluator = User::forceCreate([
        'email'        => 'other.evaluator@example.test',
        'password'     => bcrypt('password'),
        'role_id'      => \App\Models\Role::where('name', 'Admin')->value('id'),
        'profile_type' => 'Individual',
    ]);
    \App\Models\AdminProfile::create([
        'user_id'       => $otherEvaluator->id,
        'division_id'   => \App\Models\Division::where('name', 'HCD')->value('id'),
        'first_name'    => 'Other',
        'last_name'     => 'Evaluator',
        'position'      => 'LSO III',
        'admin_role_id' => \App\Models\AdminRole::where('name', 'Evaluator')->value('id'),
    ]);
    $teamLead = User::where('email', 'hcdarms2026@gmail.com')->firstOrFail();

    $this->flushSession();
    $this->actingAs($otherEvaluator)->post(route('admin.hcd.first_aider_certificates.approve', $submission->id))->assertForbidden();
    $this->flushSession();
    $this->actingAs($teamLead)->post(route('admin.hcd.first_aider_certificates.approve', $submission->id))->assertForbidden();

    expect($submission->refresh()->status)->toBe('pending');

    // The assigned evaluator sees the renewal with Approve on the application page.
    $this->flushSession();
    $this->actingAs($evaluator)
        ->get(route('admin.hcd.applications.show', $accreditation->application_id))
        ->assertOk()
        ->assertSee('First-Aider Certificate')
        ->assertSee(route('admin.hcd.first_aider_certificates.approve', $submission->id), false);
});

test('reminders go 3, 2 and 1 month before expiry, once each, then an expired notice', function () {
    [$fatpro, $accreditation] = faFixture();

    // First seen a month out: only the 1-month reminder, never all three.
    faSetValidity($accreditation, now()->addDays(20)->toDateString());
    $this->artisan('first-aider-certificate:expiry-check')->assertSuccessful();

    Mail::assertQueued(FirstAiderCertificateEmail::class, 1);
    Mail::assertQueued(FirstAiderCertificateEmail::class, fn ($m) => $m->kind === 'reminder' && $m->timeframe === '1 month');

    $accreditation->refresh();
    expect($accreditation->fa_cert_reminder_1mo_sent_at)->not->toBeNull();
    expect($accreditation->fa_cert_reminder_3mo_sent_at)->not->toBeNull();

    // Not repeated.
    $this->artisan('first-aider-certificate:expiry-check')->assertSuccessful();
    Mail::assertQueued(FirstAiderCertificateEmail::class, 1);

    // Once it has passed: the expired notice, once.
    faSetValidity($accreditation, now()->subDay()->toDateString());
    $this->artisan('first-aider-certificate:expiry-check')->assertSuccessful();
    $this->artisan('first-aider-certificate:expiry-check')->assertSuccessful();

    Mail::assertQueued(FirstAiderCertificateEmail::class, fn ($m) => $m->kind === 'expired' && $m->hasTo($fatpro->email));
    Mail::assertQueued(FirstAiderCertificateEmail::class, 2);
    expect($fatpro->notifications()->where('type', 'App\Notifications\FirstAiderCertificateNotification')->count())->toBe(2);
});

test('no notice is sent while a renewal is already waiting for review', function () {
    [$fatpro, $accreditation] = faFixture();
    faSetValidity($accreditation, now()->subDays(3)->toDateString());

    faSubmit($fatpro);
    Mail::fake(); // forget the "submitted" e-mail

    $this->artisan('first-aider-certificate:expiry-check')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('the dashboard shows an expired certificate and the way to renew it', function () {
    [$fatpro, $accreditation] = faFixture();
    faSetValidity($accreditation, now()->subDays(5)->toDateString());

    $this->actingAs($fatpro)->get(route('applicant.dashboard'))
        ->assertOk()
        ->assertSee('First-Aider Certificate')
        ->assertSee('Expired')
        ->assertSee(route('applicant.first_aider_certificate.store'), false);
});
