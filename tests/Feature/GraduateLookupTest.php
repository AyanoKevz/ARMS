<?php

use App\Mail\GraduateLookupAccessEmail;
use App\Models\Accreditation;
use App\Models\GraduateSearch;
use App\Models\GraduateVisitor;
use App\Models\NtcReport;
use App\Models\NtcTrainingMode;
use App\Models\NtcTrainingType;
use App\Models\OrganizationProfile;
use App\Models\PostTrainingReport;
use App\Models\PtrParticipant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Mail::fake();
});

/** A FATPro with one post training report in the given status. */
function graduateFixture(string $suffix, string $providerName, string $reportStatus): PostTrainingReport
{
    $applicant = User::forceCreate([
        'email'        => "grad_{$suffix}@example.com",
        'password'     => bcrypt('password'),
        'role_id'      => Role::firstOrCreate(['name' => 'Applicant'])->id,
        'profile_type' => 'Organization',
    ]);

    OrganizationProfile::create([
        'user_id'     => $applicant->id,
        'name'        => $providerName,
        'address'     => 'Test Address',
        'email'       => "grad_{$suffix}@example.com",
        'head_name'   => 'Head Name',
        'designation' => 'Director',
    ]);

    $typeId = \App\Models\AccreditationType::firstOrCreate(['name' => 'First Aid Training Providers'])->id;

    $application = \App\Models\Application::create([
        'user_id'               => $applicant->id,
        'accreditation_type_id' => $typeId,
        'application_type'      => 'new',
        'tracking_number'       => "ARMS-GRAD-{$suffix}",
    ]);

    $accreditation = Accreditation::create([
        'user_id'               => $applicant->id,
        'application_id'        => $application->id,
        'accreditation_type_id' => $typeId,
        'accreditation_number'  => "FATPRO-GRAD-{$suffix}",
        'date_of_accreditation' => now()->subYear()->format('Y-m-d'),
        'validity_date'         => now()->addYear()->format('Y-m-d'),
        'status'                => 'active',
    ]);

    $ntc = NtcReport::create([
        'accreditation_id'     => $accreditation->id,
        'ntc_training_type_id' => NtcTrainingType::first()->id,
        'ntc_training_mode_id' => NtcTrainingMode::first()->id,
        'training_start_date'  => now()->subDays(20)->format('Y-m-d'),
        'training_end_date'    => now()->subDays(18)->format('Y-m-d'),
        'status'               => 'acknowledged',
        'acknowledged_at'      => now()->subDays(30),
    ]);

    $report = PostTrainingReport::create([
        'ntc_report_id'    => $ntc->id,
        'accreditation_id' => $accreditation->id,
        'status'           => $reportStatus,
        'submitted_at'     => now()->subDays(10),
        'accepted_at'      => $reportStatus === 'accepted' ? now()->subDays(5) : null,
    ]);

    $participantStatus = $reportStatus === 'accepted' ? 'approved' : 'pending';

    foreach (['Maria' => 'Santos', 'Jose' => 'Reyes'] as $first => $last) {
        PtrParticipant::create([
            'post_training_report_id' => $report->id,
            'certificate_number'      => "CERT-{$suffix}-{$first}",
            'first_name'              => $first,
            'last_name'               => "{$last}{$suffix}",
            'sex'                     => 'Female',
            'age'                     => 30,
            'company'                 => 'Secret Employer Inc',
            'position'                => 'Staff',
            'company_city'            => 'Quezon City',
            'company_region'          => 'NCR',
            'industry'                => 'Manufacturing',
            'mobile_no'               => '09171234567',
            'mode_of_training'        => 'Face to Face',
            'status'                  => $participantStatus,
        ]);
    }

    return $report;
}

/** Request access and return the link that was e-mailed. */
function requestGraduateAccess($test, string $email = 'visitor@example.com'): string
{
    $test->post(route('graduates.request.store'), [
        'name'         => 'Ana Visitor',
        'organization' => 'Acme Hiring Co',
        'email'        => $email,
        'purpose'      => 'Verifying an applicant',
    ])->assertRedirect(route('graduates.request'));

    $url = null;

    Mail::assertSent(GraduateLookupAccessEmail::class, function ($mail) use (&$url, $email) {
        $url = $mail->accessUrl;

        return $mail->hasTo($email);
    });

    return $url;
}

test('requesting access records an unverified visitor and e-mails a link', function () {
    requestGraduateAccess($this);

    $visitor = GraduateVisitor::sole();

    expect($visitor->name)->toBe('Ana Visitor');
    expect($visitor->organization)->toBe('Acme Hiring Co');
    expect($visitor->isVerified())->toBeFalse();
});

test('the request form requires every field', function () {
    $this->post(route('graduates.request.store'), [])
        ->assertSessionHasErrors(['name', 'organization', 'email', 'purpose']);

    expect(GraduateVisitor::count())->toBe(0);
    Mail::assertNothingSent();
});

test('the search page cannot be opened without a verified link', function () {
    $this->get(route('graduates.search', ['q' => 'anything']))
        ->assertRedirect(route('graduates.request'));
});

test('a tampered or expired link does not grant access', function () {
    $url = requestGraduateAccess($this);

    $this->get($url . 'x')->assertRedirect(route('graduates.request'));

    $this->travel(GraduateVisitor::LINK_VALID_HOURS + 1)->hours();
    $this->get($url)->assertRedirect(route('graduates.request'));

    expect(GraduateVisitor::sole()->isVerified())->toBeFalse();
});

test('a verified visitor sees the graduates of accepted reports only, and the search is logged', function () {
    graduateFixture('01', 'Lifeline Training Center', 'accepted');
    graduateFixture('02', 'Lifeline Rescue Academy', 'submitted');

    $url = requestGraduateAccess($this);

    $this->get($url)->assertRedirect(route('graduates.search'));
    expect(GraduateVisitor::sole()->isVerified())->toBeTrue();

    $response = $this->get(route('graduates.search', ['q' => 'lifeline']));

    $response->assertOk()
        ->assertSee('Lifeline Training Center')
        ->assertSee('First Aid Training Providers')
        ->assertSee('Santos01')
        ->assertSee('CERT-01-Jose')
        // Still being evaluated: not graduates yet.
        ->assertDontSee('Lifeline Rescue Academy')
        ->assertDontSee('Santos02')
        // Nothing beyond name and certificate leaves the database.
        ->assertDontSee('09171234567')
        ->assertDontSee('Secret Employer Inc');

    $search = GraduateSearch::sole();
    expect($search->search_term)->toBe('lifeline');
    expect($search->results_count)->toBe(2);
});

test('the accreditation number finds the provider too', function () {
    graduateFixture('03', 'Bayanihan First Aid', 'accepted');

    $this->get(requestGraduateAccess($this));

    $this->get(route('graduates.search', ['q' => 'fatpro-grad-03']))
        ->assertOk()
        ->assertSee('Bayanihan First Aid')
        ->assertSee('Santos03');
});

test('a graduate is found by name, in any word order, with who trained them', function () {
    graduateFixture('06', 'Lifeline Training Center', 'accepted');
    graduateFixture('07', 'Pending Rescue Academy', 'submitted');

    $this->get(requestGraduateAccess($this));

    $this->get(route('graduates.search', ['q' => 'santos06 maria']))
        ->assertOk()
        ->assertSee('Santos06')
        ->assertSee('CERT-06-Maria')
        ->assertSee('Lifeline Training Center')
        // The other participant in the same training does not match.
        ->assertDontSee('Reyes06');

    // Not a graduate yet: their report is still under evaluation.
    $this->get(route('graduates.search', ['q' => 'santos07']))
        ->assertOk()
        ->assertSee('No Graduates Found')
        ->assertDontSee('CERT-07-Maria');

    expect(GraduateSearch::where('search_term', 'santos06 maria')->value('results_count'))->toBe(1);
});

test('a graduate is found by the full certificate number', function () {
    graduateFixture('08', 'Lifeline Training Center', 'accepted');

    $this->get(requestGraduateAccess($this));

    $this->get(route('graduates.search', ['q' => 'cert-08-jose']))
        ->assertOk()
        ->assertSee('Reyes08')
        ->assertDontSee('Santos08')
        ->assertDontSee('09171234567');
});

test('a search term shorter than three characters is not run', function () {
    graduateFixture('04', 'Lifeline Training Center', 'accepted');

    $this->get(requestGraduateAccess($this));

    $this->get(route('graduates.search', ['q' => 'li']))
        ->assertOk()
        ->assertSee('at least 3 characters')
        ->assertDontSee('Santos04');

    expect(GraduateSearch::count())->toBe(0);
});

test('HCD admins see the visitor log with what each visitor searched', function () {
    graduateFixture('05', 'Lifeline Training Center', 'accepted');

    $this->get(requestGraduateAccess($this));
    $this->get(route('graduates.search', ['q' => 'lifeline']));

    $teamLead = User::where('email', 'hcdarms2026@gmail.com')->firstOrFail();

    $this->actingAs($teamLead)->get(route('admin.hcd.graduate_visitors.index'))->assertOk();

    $json = $this->actingAs($teamLead)
        ->getJson(route('admin.hcd.graduate_visitors.data', ['draw' => 1]))
        ->assertOk()
        ->json();

    expect($json['recordsTotal'])->toBe(1);

    $row = implode(' ', $json['data'][0]);
    expect($row)->toContain('Ana Visitor')
        ->toContain('visitor@example.com')
        ->toContain('Acme Hiring Co')
        ->toContain('Verified')
        ->toContain('"lifeline"');
});

test('the Accreditation division cannot open the visitor log', function () {
    $verifier = User::where('email', 'oshc1987accreditation@gmail.com')->firstOrFail();

    $this->actingAs($verifier)->get(route('admin.hcd.graduate_visitors.index'))->assertForbidden();
    $this->actingAs($verifier)->getJson(route('admin.hcd.graduate_visitors.data'))->assertForbidden();
});
