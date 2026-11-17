<?php

namespace App\Support;

use App\Models\Accreditation;
use App\Models\Application;
use App\Services\PractitionerApplicationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Display data for the DOLE-BWC AF-PCN-A1 application form (new
 * applicants), and for the AF-PCR-A2 renewal form (renewal*() below).
 *
 * The same Blade partials (resources/views/practitioner/form) render the
 * applicant's "Review Details" preview, and the PDF the Accreditation admins
 * open. Both feed them through here, so the preview always matches the
 * attached form. The renewal form has its own pages
 * (resources/views/practitioner/renewal-form) on the same partials.
 *
 * Rules carried over from the paper form:
 *   - an optional single-value field left blank reads "N/A"
 *     (display only — nothing is stored);
 *   - every table shows at least the paper form's row count, and unused rows
 *     stay completely blank.
 */
final class PractitionerForm
{
    public const NA = 'N/A';

    /** Rows printed on the paper form for each table. */
    public const ROWS = [
        'education'    => 3,
        'work'         => 4,
        'trainings'    => 4,
        'lectures'     => 4,
        'skills'       => 3,
        'awards'       => 3,
        'examinations' => 2,
        'memberships'  => 3,
        'references'   => 3,
    ];

    /** The AF-PCR-A2's header block (practitioner.form._header's $sheet). */
    public const RENEWAL_SHEET = [
        'kind'         => 'Renewal',
        'code'         => 'AF-PCR-A2',
        'revision'     => '0703-0',
        'pages'        => 2,
        'instructions' => 'Fill in all the data needed. Use block/printed letters or use a typewriter. Write N.A. if the blanks are not applicable. Application may be submitted directly to BWC or to concerned R.O. Documents submitted must be signed in all pages.',
    ];

    /** Rows printed on the AF-PCR-A2 renewal form for each of its tables. */
    public const RENEWAL_ROWS = [
        'work'      => 3,
        'trainings' => 4,
        'lectures'  => 4,
        'skills'    => 3,
        'awards'    => 3,
    ];

    /** Application relation holding each CV section's rows. */
    public const SECTION_RELATIONS = [
        'education'    => 'practitionerEducations',
        'licenses'     => 'practitionerLicenses',
        'work'         => 'practitionerWorkExperiences',
        'trainings'    => 'practitionerTrainings',
        'lectures'     => 'practitionerLectures',
        'skills'       => 'practitionerSkills',
        'awards'       => 'practitionerAwards',
        'examinations' => 'practitionerExaminations',
        'memberships'  => 'practitionerMemberships',
        'references'   => 'practitionerReferences',
    ];

    /**
     * From the registration form, for the "Review Details" preview.
     *
     * @param array    $core          first_name, middle_name, last_name, suffix, sex, birthday, region, city, address
     * @param array    $practitioner  the raw practitioner[...] input
     * @param int|null $typeId        the accreditation type applied for (Practitioner when not given)
     */
    public static function fromInput(array $core, array $practitioner, ?string $email, ?int $typeId = null): array
    {
        $data = app(PractitionerApplicationService::class)->normalize($practitioner);

        return self::build($core, $data, [
            'email'       => $email,
            'photo_src'   => null,
            'signed_date' => Carbon::now(),
            'applying_as' => self::applyingAs($typeId),
        ]);
    }

    /** From a submitted application, for the attached PDF. */
    public static function fromApplication(Application $application): array
    {
        ['core' => $core, 'data' => $data] = self::storedValues($application);
        $prac = $application->practitionerProfile;

        return self::build($core, $data, [
            'email'           => $application->user?->email,
            'photo_src'       => self::photoDataUri($prac?->photo_path),
            'signed_date'     => $application->submitted_at ?? $application->created_at,
            'tracking_number' => $application->tracking_number,
            'applying_as'     => self::applyingAs($application->accreditation_type_id),
        ]);
    }

    /**
     * AF-PCR-A2 from the renewal form, for its "Review Details" preview.
     * Only what the renewal form prints is read: the fields the portal keeps
     * locked (date of birth, education, references…) are not on it.
     *
     * @param array $core          first_name, middle_name, last_name, suffix, sex, region, city, address
     * @param array $practitioner  the raw practitioner[...] input
     */
    public static function renewalFromInput(array $core, array $practitioner, ?string $email, Accreditation $accreditation): array
    {
        $data = app(PractitionerApplicationService::class)->normalize($practitioner);

        return self::buildRenewal($core, $data, [
            'email'         => $email,
            'signed_date'   => Carbon::now(),
            'applying_as'   => self::applyingAs($accreditation->accreditation_type_id),
            'accreditation' => self::accreditationDetails($accreditation),
        ]);
    }

    /**
     * The box beside "I would like to apply for renewal of my accreditation
     * as": the accreditation number, when it was first accredited, its last
     * renewal (none yet when it came from a new application) and its validity.
     *
     * @return array{number: string, accredited: string, last_renewal: string, validity: string}
     */
    public static function accreditationDetails(Accreditation $accreditation): array
    {
        $accreditation->loadMissing('application');

        $first = Accreditation::where('user_id', $accreditation->user_id)
            ->where('accreditation_type_id', $accreditation->accreditation_type_id)
            ->orderBy('date_of_accreditation')
            ->orderBy('id')
            ->first() ?? $accreditation;

        $renewed = in_array($accreditation->application?->application_type, ['renewal', 'reinstatement'], true);

        return [
            'number'       => self::na($accreditation->accreditation_number),
            'accredited'   => self::na($first->date_of_accreditation?->format('m/d/Y')),
            'last_renewal' => $renewed ? self::na($accreditation->date_of_accreditation?->format('m/d/Y')) : self::NA,
            'validity'     => self::na($accreditation->validity_date?->format('m/d/Y')),
        ];
    }

    /** The box ticked under "I would like to apply for Accreditation as": "practitioner" / "consultant". */
    private static function applyingAs(?int $typeId): string
    {
        return PractitionerApplicationService::typeKey($typeId) ?? 'practitioner';
    }

    /**
     * The applicant's answers as stored: the core person fields, and the rest
     * in the shape PractitionerApplicationService::normalize() produces. What
     * the PDF is built from, and what the tracking page fills the form with
     * when a rejected application form has to be corrected.
     *
     * @return array{core: array<string, mixed>, data: array<string, mixed>}
     */
    public static function storedValues(Application $application): array
    {
        $application->loadMissing(array_merge(
            ['user.individualProfile.workplaceProfile', 'practitionerProfile'],
            array_values(self::SECTION_RELATIONS)
        ));

        $profile   = $application->user?->individualProfile;
        $workplace = $profile?->workplaceProfile;
        $prac      = $application->practitionerProfile;

        $sections = [];
        foreach (PractitionerApplicationService::SECTIONS as $key => [, $fields]) {
            $sections[$key] = $application->{self::SECTION_RELATIONS[$key]}
                ->map(fn ($row) => collect($fields)->mapWithKeys(
                    fn ($f) => [$f => $row->{$f} instanceof Carbon ? $row->{$f}->format('Y-m-d') : $row->{$f}]
                )->all())
                ->all();
        }

        $data = [
            'profile'      => collect(PractitionerApplicationService::PROFILE_FIELDS)
                ->mapWithKeys(fn ($f) => [$f => $profile?->{$f}])->all(),
            'workplace'    => collect(PractitionerApplicationService::WORKPLACE_FIELDS)
                ->mapWithKeys(fn ($f) => [$f => $workplace?->{$f}])->all(),
            'experience'   => ['total_osh_experience_years' => $prac?->total_osh_experience_years ?? 0],
            'declarations' => collect(PractitionerApplicationService::declarationColumns())
                ->mapWithKeys(fn ($f) => [$f => $prac?->{$f}])->all(),
            'sections'     => $sections,
        ];

        $core = [
            'first_name'  => $profile?->first_name,
            'middle_name' => $profile?->middle_name,
            'last_name'   => $profile?->last_name,
            'suffix'      => $profile?->suffix,
            'sex'         => $profile?->sex,
            'birthday'    => $profile?->birthday?->format('Y-m-d'),
            'region'      => $profile?->region,
            'city'        => $profile?->city,
            'address'     => $profile?->address,
        ];

        return ['core' => $core, 'data' => $data];
    }

    private static function build(array $core, array $data, array $meta): array
    {
        $p = $data['profile'];
        $w = $data['workplace'];
        $s = $data['sections'];

        $industry = ($p['type_of_industry'] ?? null) === 'Other'
            ? ($p['type_of_industry_other'] ?? null)
            : ($p['type_of_industry'] ?? null);

        $cityAddress = collect([$core['address'] ?? null, $core['city'] ?? null])
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter()
            ->implode(', ');

        // The profile prints the suffix in its own cell after the middle name;
        // the signature line carries the whole name ("Juan Santos Dela Cruz, Jr.").
        $suffix = is_string($core['suffix'] ?? null) ? trim($core['suffix']) : '';

        $fullName = collect([$core['first_name'] ?? null, $core['middle_name'] ?? null, $core['last_name'] ?? null])
            ->filter()
            ->implode(' ') . ($suffix !== '' ? ', ' . $suffix : '');

        // The paper form has one line for the professional license; when the
        // applicant listed several, each value is joined in the same order.
        $licenses = $s['licenses'] ?? [];
        $license  = fn (string $field, bool $date = false) => self::na(
            collect($licenses)->pluck($field)->filter()
                ->map(fn ($v) => $date ? self::date($v) : $v)
                ->implode('; ')
        );

        return [
            'tracking_number' => $meta['tracking_number'] ?? null,
            'photo_src'       => $meta['photo_src'],
            'applying_as'     => $meta['applying_as'] ?? 'practitioner',

            'name' => [
                'last'   => self::na($core['last_name'] ?? null),
                'first'  => self::na($core['first_name'] ?? null),
                'middle' => self::na($core['middle_name'] ?? null),
                'suffix' => self::na($suffix),
            ],
            'sex'          => $core['sex'] ?? null,
            'civil_status' => $p['civil_status'] ?? null,
            'hazard_level' => $w['hazard_level'] ?? null,

            'field' => [
                'city_address'     => self::na($cityAddress),
                'birthday'         => self::na($core['birthday'] ?? null),
                'citizenship'      => self::na($p['citizenship'] ?? null),
                'home_address'     => self::na($p['home_provincial_address'] ?? null),
                'height'           => self::na(self::number($p['height_cm'] ?? null)),
                'religion'         => self::na($p['religion'] ?? null),
                'business_address' => self::na($p['business_address'] ?? null),
                'weight'           => self::na(self::number($p['weight_kg'] ?? null)),
                'tin'              => self::na($p['tin_no'] ?? null),
                'prc_no'           => self::na($p['prc_license_no'] ?? null),
                'nature'           => self::na($industry),
                'blood_type'       => self::na($p['blood_type'] ?? null),
                'sss'              => self::na($p['sss_gsis_no'] ?? null),
                'cellular'         => self::na($p['contact_number'] ?? null),
                'home_tel'         => self::na($p['home_telephone_no'] ?? null),
                'co_tel'           => self::na($p['company_telephone_no'] ?? null),
                'email'            => self::na($meta['email'] ?? null),
                // Not collected online; printed for parity with the paper form.
                'fax'              => self::NA,
                'psic'             => self::na($w['psic_code'] ?? null),
                'region'           => self::na($w['region_of_employment'] ?? null),
                'geo'              => self::na($w['geo_code'] ?? null),
                'zip'              => self::na($w['zip_code'] ?? null),
                'male'             => self::na($w['employees_male'] ?? null),
                'female'           => self::na($w['employees_female'] ?? null),
                'total'            => self::na($w['total_workforce_size'] ?? null),
                'license_type'     => $license('license_type'),
                'license_no'       => $license('prc_license_no'),
                'license_issued'   => $license('date_issued', true),
                'license_validity' => $license('validity_period'),
            ],

            'osh_years' => (int) ($data['experience']['total_osh_experience_years'] ?? 0),

            'tables' => [
                'education' => self::rows('education', $s, fn ($r) => [
                    $r['degree'], $r['school_name_address'],
                    self::range($r['date_from'], $r['date_to']), $r['awards_honors'],
                ]),
                'work' => self::rows('work', $s, fn ($r) => [
                    $r['designation'], self::date($r['date_from']),
                    $r['date_to'] ? self::date($r['date_to']) : ($r['date_from'] ? 'Present' : null),
                    $r['length_of_service'], $r['appointment_status'], $r['company'],
                ]),
                'trainings' => self::rows('trainings', $s, fn ($r) => [
                    $r['title'], self::date($r['date_from']), self::date($r['date_to']),
                    self::number($r['no_of_hours']), $r['conducted_by'], $r['venue'],
                ]),
                'lectures' => self::rows('lectures', $s, fn ($r) => [
                    $r['topic'], self::date($r['date_from']), self::date($r['date_to']),
                    self::number($r['no_of_hours']), $r['conducted_for'], $r['venue'],
                ]),
                'skills' => self::rows('skills', $s, fn ($r) => [
                    $r['trade_occupation'], $r['field_of_expertise'], $r['description'], $r['years_of_experience'],
                ]),
                'awards' => self::rows('awards', $s, fn ($r) => [
                    $r['title'], $r['issued_by'], self::date($r['date_issued']),
                ]),
                'examinations' => self::rows('examinations', $s, fn ($r) => [
                    $r['title'], $r['year_taken'], $r['given_by'], $r['rating'],
                ]),
                'memberships' => self::rows('memberships', $s, fn ($r) => [
                    $r['organization'], $r['designation'], $r['validity'],
                ]),
                'references' => self::rows('references', $s, fn ($r) => [
                    $r['full_name'], $r['designation'], $r['company_address'], $r['contact_number'],
                ]),
            ],

            'declarations'        => $data['declarations'],
            'declaration_details' => self::declarationDetails($data['declarations']),

            'signature'   => self::na($fullName),
            'signed_date' => $meta['signed_date'] ? Carbon::parse($meta['signed_date'])->format('m/d/Y') : '',
        ];
    }

    /**
     * The AF-PCR-A2 renewal form: the profile without the personal details
     * the renewal does not ask again, and sections 2 and 4 – 7.
     */
    private static function buildRenewal(array $core, array $data, array $meta): array
    {
        $p = $data['profile'];
        $w = $data['workplace'];
        $s = $data['sections'];

        $industry = ($p['type_of_industry'] ?? null) === 'Other'
            ? ($p['type_of_industry_other'] ?? null)
            : ($p['type_of_industry'] ?? null);

        $cityAddress = collect([$core['address'] ?? null, $core['city'] ?? null])
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter()
            ->implode(', ');

        $suffix   = is_string($core['suffix'] ?? null) ? trim($core['suffix']) : '';
        $fullName = collect([$core['first_name'] ?? null, $core['middle_name'] ?? null, $core['last_name'] ?? null])
            ->filter()
            ->implode(' ') . ($suffix !== '' ? ', ' . $suffix : '');

        // "Total" under the trainings and lectures tables: their hours added up.
        $hours = function (string $key) use ($s): string {
            $values = collect($s[$key] ?? [])->pluck('no_of_hours')->filter(fn ($v) => is_numeric($v));

            return $values->isEmpty() ? '' : (string) self::number($values->sum());
        };

        return [
            'applying_as'   => $meta['applying_as'] ?? 'practitioner',
            'accreditation' => $meta['accreditation'],

            'name' => [
                'last'   => self::na($core['last_name'] ?? null),
                'first'  => self::na($core['first_name'] ?? null),
                'middle' => self::na($core['middle_name'] ?? null),
                'suffix' => self::na($suffix),
            ],
            'sex'          => $core['sex'] ?? null,
            'civil_status' => $p['civil_status'] ?? null,
            'hazard_level' => $w['hazard_level'] ?? null,

            'field' => [
                'city_address'     => self::na($cityAddress),
                'home_address'     => self::na($p['home_provincial_address'] ?? null),
                'home_tel'         => self::na($p['home_telephone_no'] ?? null),
                'cellular'         => self::na($p['contact_number'] ?? null),
                'business_address' => self::na($p['business_address'] ?? null),
                'co_tel'           => self::na($p['company_telephone_no'] ?? null),
                // Not collected online; printed for parity with the paper form.
                'fax'              => self::NA,
                'email'            => self::na($meta['email'] ?? null),
                'nature'           => self::na($industry),
                'psic'             => self::na($w['psic_code'] ?? null),
                'male'             => self::na($w['employees_male'] ?? null),
                'female'           => self::na($w['employees_female'] ?? null),
                'total'            => self::na($w['total_workforce_size'] ?? null),
                'region'           => self::na($w['region_of_employment'] ?? null),
                'geo'              => self::na($w['geo_code'] ?? null),
            ],

            'osh_years' => (int) ($data['experience']['total_osh_experience_years'] ?? 0),

            'tables' => [
                'work' => self::rows('work', $s, fn ($r) => [
                    $r['designation'], self::date($r['date_from']),
                    $r['date_to'] ? self::date($r['date_to']) : ($r['date_from'] ? 'Present' : null),
                    $r['length_of_service'], $r['appointment_status'], $r['company'],
                ], self::RENEWAL_ROWS['work']),
                'trainings' => self::rows('trainings', $s, fn ($r) => [
                    $r['title'], self::date($r['date_from']), self::date($r['date_to']),
                    self::number($r['no_of_hours']), $r['conducted_by'], $r['venue'],
                ], self::RENEWAL_ROWS['trainings']),
                'lectures' => self::rows('lectures', $s, fn ($r) => [
                    $r['topic'], self::date($r['date_from']), self::date($r['date_to']),
                    self::number($r['no_of_hours']), $r['conducted_for'], $r['venue'],
                ], self::RENEWAL_ROWS['lectures']),
                'skills' => self::rows('skills', $s, fn ($r) => [
                    $r['trade_occupation'], $r['field_of_expertise'], $r['description'], $r['years_of_experience'],
                ], self::RENEWAL_ROWS['skills']),
                'awards' => self::rows('awards', $s, fn ($r) => [
                    $r['title'], $r['description'], $r['issued_by'], self::date($r['date_issued']),
                ], self::RENEWAL_ROWS['awards']),
            ],
            'totals' => [
                'trainings' => $hours('trainings'),
                'lectures'  => $hours('lectures'),
            ],

            'signature'   => self::na($fullName),
            'signed_date' => $meta['signed_date'] ? Carbon::parse($meta['signed_date'])->format('m/d/Y') : '',
        ];
    }

    /**
     * One table's display rows, padded with blank rows to the paper form's
     * count ($min, else ROWS). Cells are strings; a blank cell inside a
     * filled row stays blank.
     *
     * @return array<int, array<int, string>>
     */
    private static function rows(string $key, array $sections, callable $map, ?int $min = null): array
    {
        $rows = collect($sections[$key] ?? [])
            ->map(fn ($r) => array_map(fn ($v) => $v === null ? '' : (string) $v, $map($r)))
            ->values()
            ->all();

        $width = count($map(array_fill_keys(
            PractitionerApplicationService::SECTIONS[$key][1], null
        )));

        while (count($rows) < ($min ?? self::ROWS[$key])) {
            $rows[] = array_fill(0, $width, '');
        }

        return $rows;
    }

    /**
     * The "give details" lines under the declarations: the applicant's text
     * when a question they cover is answered Yes, N/A when none is. A Yes
     * still waiting for its details (a preview mid-form) stays blank.
     *
     * @return array<string, string>
     */
    private static function declarationDetails(array $declarations): array
    {
        $lines = [];
        foreach (PractitionerApplicationService::DECLARATION_DETAILS as $field => $questions) {
            $yes = collect($questions)->contains(fn ($q) => ($declarations[$q] ?? null) === true);

            $lines[$field] = $yes ? trim((string) ($declarations[$field] ?? '')) : self::NA;
        }

        return $lines;
    }

    private static function na(mixed $value): string
    {
        if ($value === null) {
            return self::NA;
        }

        $value = trim((string) $value);

        return $value === '' ? self::NA : $value;
    }

    private static function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('m/d/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private static function range(mixed $from, mixed $to): ?string
    {
        $from = self::date($from);
        $to   = self::date($to);

        if (! $from && ! $to) {
            return null;
        }

        return trim(($from ?? '') . ' – ' . ($to ?? ''), ' –');
    }

    /** 170.00 → "170", 65.50 → "65.5". */
    private static function number(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /** The ID photo inlined for dompdf, which cannot read the private disk. */
    private static function photoDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = str_ends_with(strtolower($path), '.png') ? 'image/png' : 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('local')->get($path));
    }
}
