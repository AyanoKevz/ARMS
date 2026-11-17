{{--
    OSH Practitioner accreditation — form body.

    Shown when accreditation type 1 (Practitioners) or 2 (Consultant) is
    selected. The core person
    fields (name, sex, date of birth, region, city, city address) are NOT
    repeated here — they already live in #individualFields above and are wired to
    landing.js's IND_REQUIRED list. This partial adds the practitioner-specific
    profile columns, the ten repeatable CV sections, the disclosures and the
    documentary checklist.

    Submitted through RegistrationController (validated and stored by
    PractitionerApplicationService). Field names match
    2026_09_13_000001_create_practitioner_tables.php. "Review Details" renders
    these values as the AF-PCN-A1 form (resources/views/practitioner/form).
    Text in the $title of a repeater is escaped on output — write "&", not "&amp;".

    Also rendered on the tracking page with $correcting = true and the stored
    answers in $pracValues, when the Accreditation division rejects the
    application form: Steps 3–13 come back filled in for the applicant to
    correct, the ID photo is optional (one is on file) and Step 14's uploads
    are left out.

    OSH Consultant (type 2) applies with this same form. What differs — the
    photo background and Step 14's checklist — is rendered for both types
    with data-prac-for="<type id>", and practitioner-form.js shows (and
    enables) only the selected type's. $pracType, when given (the tracking
    page knows the application's type), renders only that type's.

    $renewal = true: the practitioner portal's renewal form (DOLE-BWC
    AF-PCR-A2, applicant/practitioner/renewal.blade.php), filled in from
    $pracValues like a correction. The renewal page puts the person fields in
    its own Step 1, so the steps here count one lower. What the AF-PCR-A2
    does not ask again stays visible but locked (disabled, so it is neither
    validated nor sent): the fields marked 'renewalLocked' and the whole
    education, examination, membership, reference and declaration steps.
    The ID photo and the renewal's documentary requirements make up the last
    step, posted with $previewUrl for its Review Details.
--}}
@php
    $opt        = config('accreditation');
    $correcting = $correcting ?? false;
    $renewal    = $renewal ?? false;
    $pracType   = isset($pracType) ? (int) $pracType : null;
    $typeIds    = ['practitioner' => (int) $opt['types']['practitioner'], 'consultant' => (int) $opt['types']['consultant']];
    $step       = fn (int $number) => $renewal ? $number - 1 : $number;
@endphp

{{-- Every page this form is on (registration, tracking page, portal renewal) needs its controls' styles. --}}
@pushOnce('styles')
<link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/practitioner-fields.css') }}">
@endPushOnce

<div id="practitionerSections" @class(['d-none' => ! $correcting && ! $renewal]) data-preview-url="{{ $previewUrl ?? route('register.practitioner.preview') }}">

    {{-- The requirements notice for this type lives in
         landing.partials.practitioner.notice, rendered beside
         #fatproRequirementsNotice so it is on screen before Step 2. --}}

    {{-- ── Personal profile (continues #individualFields above) ─────────── --}}
    <p class="form-section-title">Step {{ $step(3) }} &mdash; Personal Profile <span class="prac-step-sub">(continued)</span></p>

    <div class="row g-3">
        @foreach([
            ['name' => 'civil_status',  'label' => 'Civil Status',  'type' => 'select', 'col' => 'col-md-4', 'required' => true, 'options' => $opt['civil_statuses']],
            ['name' => 'citizenship',   'label' => 'Citizenship',   'type' => 'text',   'col' => 'col-md-4', 'required' => true, 'placeholder' => 'e.g. Filipino', 'renewalLocked' => true],
            ['name' => 'religion',      'label' => 'Religion',      'type' => 'text',   'col' => 'col-md-4', 'placeholder' => '(optional)', 'renewalLocked' => true],
            ['name' => 'height_cm',     'label' => 'Height (cm)',   'type' => 'number', 'col' => 'col-md-4', 'min' => 0, 'step' => '0.01', 'placeholder' => 'e.g. 170', 'renewalLocked' => true],
            ['name' => 'weight_kg',     'label' => 'Weight (kg)',   'type' => 'number', 'col' => 'col-md-4', 'min' => 0, 'step' => '0.01', 'placeholder' => 'e.g. 65', 'renewalLocked' => true],
            ['name' => 'blood_type',    'label' => 'Blood Type',    'type' => 'select', 'col' => 'col-md-4', 'options' => $opt['blood_types'], 'renewalLocked' => true],
        ] as $f)
            @include('landing.partials.practitioner.field', ['f' => $f, 'group' => 'practitioner[profile]', 'idPrefix' => 'prac_profile'])
        @endforeach
    </div>

    <p class="prac-subheading mt-4">Addresses</p>
    <div class="row g-3">
        @foreach([
            ['name' => 'home_provincial_address', 'label' => 'Home / Provincial Address', 'type' => 'textarea', 'col' => 'col-md-6', 'rows' => 2, 'placeholder' => '(optional)'],
            ['name' => 'business_address',        'label' => 'Business / Company Address', 'type' => 'textarea', 'col' => 'col-md-6', 'rows' => 2, 'hint' => '(if corporate-employed)', 'placeholder' => '(optional)'],
        ] as $f)
            @include('landing.partials.practitioner.field', ['f' => $f, 'group' => 'practitioner[profile]', 'idPrefix' => 'prac_profile'])
        @endforeach
    </div>

    <p class="prac-subheading mt-4">Identification &amp; Contact</p>
    <div class="row g-3">
        @foreach([
            ['name' => 'tin_no',              'label' => 'TIN No.',             'type' => 'text', 'col' => 'col-md-4', 'placeholder' => '(optional)', 'renewalLocked' => true],
            ['name' => 'prc_license_no',      'label' => 'PRC License No.',     'type' => 'text', 'col' => 'col-md-4', 'hint' => '(if any)', 'placeholder' => '(optional)', 'renewalLocked' => true],
            ['name' => 'sss_gsis_no',         'label' => 'SSS / GSIS No.',      'type' => 'text', 'col' => 'col-md-4', 'placeholder' => '(optional)', 'renewalLocked' => true],
            ['name' => 'contact_number',      'label' => 'Contact Number',      'type' => 'tel',  'col' => 'col-md-4', 'placeholder' => '09XXXXXXXXX'],
            ['name' => 'home_telephone_no',   'label' => 'Home Telephone No.',  'type' => 'tel',  'col' => 'col-md-4', 'placeholder' => 'e.g. 8123-4567'],
            ['name' => 'company_telephone_no','label' => 'Company Tel. No.',    'type' => 'tel',  'col' => 'col-md-4', 'placeholder' => 'e.g. 8123-4567'],
        ] as $f)
            @include('landing.partials.practitioner.field', ['f' => $f, 'group' => 'practitioner[profile]', 'idPrefix' => 'prac_profile'])
        @endforeach

        @include('landing.partials.practitioner.field', [
            'f' => ['name' => 'type_of_industry', 'label' => 'Type of Industry', 'type' => 'select', 'col' => 'col-md-6', 'required' => true,
                    'options' => $opt['industries'], 'attrs' => 'data-industry-select="1"'],
            'group' => 'practitioner[profile]', 'idPrefix' => 'prac_profile',
        ])

        {{-- Revealed only when "Other" is picked above. Marked required so it
             carries the asterisk; practitioner-form.js turns `required` off
             while it is hidden. --}}
        <div class="col-md-6" id="pracIndustryOtherWrap" hidden>
            @include('landing.partials.practitioner.field', [
                'f' => ['name' => 'type_of_industry_other', 'label' => 'Please specify industry', 'type' => 'text', 'col' => 'w-100', 'required' => true, 'placeholder' => 'Specify your industry'],
                'group' => 'practitioner[profile]', 'idPrefix' => 'prac_profile',
            ])
        </div>

        {{-- The renewal asks for the photo as item 1 of its checklist instead. --}}
        @unless($renewal)
        @include('landing.partials.practitioner.field', [
            'f' => ['name' => 'photo', 'label' => '1" x 1" ID Photo', 'type' => 'file', 'col' => 'col-md-6', 'required' => ! $correcting,
                    // Red background for a Practitioner, blue for a Consultant.
                    'help' => collect(['practitioner' => 'red', 'consultant' => 'blue'])
                        ->mapWithKeys(fn ($colour, $type) => [$typeIds[$type] => $correcting
                            ? 'Your photo is already on file. Choose one only to replace it with a 1" x 1" ID picture on a ' . $colour . ' background. JPG or PNG, max 15 MB.'
                            : 'Recent 1" x 1" ID picture with a ' . $colour . ' background. Printed on your application form. JPG or PNG, max 15 MB.'])
                        ->all(),
                    'accept' => 'image/jpeg,image/png', 'allowedExt' => 'jpg,jpeg,png', 'attrs' => 'data-prac-photo-input="1"'],
            'group' => 'practitioner[profile]', 'idPrefix' => 'prac_profile',
        ])
        @endunless
    </div>

    {{-- ── Workplace character & demographics ───────────────────────────── --}}
    <p class="prac-subheading mt-4">Workplace Character &amp; Demographics</p>
    {{-- Two per row, paired so related fields sit side by side. --}}
    <div class="row g-3">
        @foreach([
            ['name' => 'hazard_level',        'label' => 'Workplace Hazard Level', 'type' => 'select', 'col' => 'col-md-6', 'options' => $opt['hazard_levels']],
            ['name' => 'region_of_employment','label' => 'Region of Employment',   'type' => 'select', 'col' => 'col-md-6', 'required' => true, 'options' => $opt['regions']],
            ['name' => 'employees_male',      'label' => 'Employees — Male',       'type' => 'number', 'col' => 'col-md-6', 'min' => 0, 'attrs' => 'data-workforce-count="1"', 'placeholder' => '0'],
            ['name' => 'employees_female',    'label' => 'Employees — Female',     'type' => 'number', 'col' => 'col-md-6', 'min' => 0, 'attrs' => 'data-workforce-count="1"', 'placeholder' => '0'],
            ['name' => 'total_workforce_size','label' => 'Total Workforce Size',   'type' => 'number', 'col' => 'col-md-6', 'min' => 0, 'hint' => '(computed from the counts above)', 'attrs' => 'data-workforce-total="1" readonly'],
            ['name' => 'psic_code',           'label' => 'PSIC Code',              'type' => 'text',   'col' => 'col-md-6', 'placeholder' => '(optional)'],
            ['name' => 'geo_code',            'label' => 'GEO Code',               'type' => 'text',   'col' => 'col-md-6', 'placeholder' => '(optional)'],
            ['name' => 'zip_code',            'label' => 'Zip Code',               'type' => 'text',   'col' => 'col-md-6', 'placeholder' => '(optional)', 'renewalLocked' => true],
        ] as $f)
            @include('landing.partials.practitioner.field', ['f' => $f, 'group' => 'practitioner[workplace]', 'idPrefix' => 'prac_workplace'])
        @endforeach
    </div>

    {{-- ── Step 4: Educational attainment ───────────────────────────────── --}}
    <p class="form-section-title mt-4">Step {{ $step(4) }} &mdash; Educational Attainment</p>

    {{-- Not on the AF-PCR-A2: shown, locked, on a renewal. --}}
    <fieldset class="prac-fieldset" @disabled($renewal)>
    @includeWhen($renewal, 'landing.partials.practitioner.locked_note')

    @include('landing.partials.practitioner.repeater', [
        'pracLocked' => $renewal,
        'key'       => 'education',
        'title'     => 'Degrees / Units Earned',
        'icon'      => 'bi-mortarboard-fill',
        'itemLabel' => 'Degree',
        'addLabel'  => 'Add Another Degree',
        'fields'    => [
            ['name' => 'degree',              'label' => 'Degree / Units Earned',            'type' => 'text',     'col' => 'col-md-6', 'required' => true, 'placeholder' => 'e.g. BS Industrial Engineering'],
            ['name' => 'school_name_address', 'label' => 'School Name & Address',            'type' => 'text',     'col' => 'col-md-6', 'required' => true, 'hint' => '(last attended)'],
            ['name' => 'date_from',           'label' => 'Inclusive Dates — From',           'type' => 'date',     'col' => 'col-md-3'],
            ['name' => 'date_to',             'label' => 'Inclusive Dates — To',             'type' => 'date',     'col' => 'col-md-3'],
            ['name' => 'awards_honors',       'label' => 'Awards / Honors Received',         'type' => 'text',     'col' => 'col-md-6', 'placeholder' => '(optional)'],
        ],
    ])

    @include('landing.partials.practitioner.repeater', [
        'pracLocked' => $renewal,
        'key'       => 'licenses',
        'title'     => 'Official Professional Licenses',
        'icon'      => 'bi-patch-check-fill',
        'itemLabel' => 'License',
        'addLabel'  => 'Add Another License',
        'note'      => 'Professional licenses issued by the PRC or an equivalent regulating body. Leave blank if none.',
        'fields'    => [
            ['name' => 'license_type',    'label' => 'Type of Professional License', 'type' => 'text', 'col' => 'col-md-6', 'placeholder' => 'e.g. Registered Chemical Engineer'],
            ['name' => 'prc_license_no',  'label' => 'PRC License No.',              'type' => 'text', 'col' => 'col-md-6'],
            ['name' => 'date_issued',     'label' => 'Date Issued',                  'type' => 'date', 'col' => 'col-md-6'],
            ['name' => 'validity_period', 'label' => 'Validity Period',              'type' => 'text', 'col' => 'col-md-6', 'placeholder' => 'e.g. 2025–2028'],
        ],
    ])
    </fieldset>

    {{-- ── Step 5: Work experience ──────────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step {{ $step(5) }} &mdash; Work Experience
        @if($renewal)<span class="prac-step-sub">(since the last issuance of your accreditation)</span>@endif
    </p>

    <div class="row g-3 mb-3">
        @include('landing.partials.practitioner.field', [
            'f' => ['name' => 'total_osh_experience_years', 'label' => 'Total Years of OSH Experience', 'type' => 'number',
                    'col' => 'col-md-4', 'required' => true, 'min' => 0, 'max' => 80, 'value' => '0'],
            'group' => 'practitioner[experience]', 'idPrefix' => 'prac_experience',
        ])
    </div>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'work',
        'title'     => 'Employment History',
        'icon'      => 'bi-briefcase-fill',
        'itemLabel' => 'Experience',
        'addLabel'  => 'Add Another Experience',
        'note'      => $renewal
            ? 'Filled in from your last application. Update it to cover your work <strong>since the last issuance of your accreditation</strong>, from the most recent, and leave "To" blank for your present position.'
            : 'List your work experience from the <strong>most recent to the present</strong>.',
        'fields'    => [
            ['name' => 'company',            'label' => 'Company / Organization',   'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'designation',        'label' => 'Designation / Position',   'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'date_from',          'label' => 'Inclusive Dates — From',   'type' => 'date',   'col' => 'col-md-6', 'attrs' => 'data-service-from="1"'],
            ['name' => 'date_to',            'label' => 'Inclusive Dates — To',     'type' => 'date',   'col' => 'col-md-6', 'hint' => '(leave blank if present)', 'attrs' => 'data-service-to="1"'],
            ['name' => 'length_of_service',  'label' => 'Length of Service',        'type' => 'text',   'col' => 'col-md-6', 'hint' => '(computed from the dates)', 'attrs' => 'data-service-length="1" readonly'],
            ['name' => 'appointment_status', 'label' => 'Status of Appointment',    'type' => 'select', 'col' => 'col-md-6', 'options' => config('accreditation.appointment_statuses')],
        ],
    ])

    {{-- ── Step 6: Trainings attended ───────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step {{ $step(6) }} &mdash; OSH-Related Trainings / Seminars Attended
        @if($renewal)<span class="prac-step-sub">(since the last issuance of your accreditation)</span>@endif
    </p>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'trainings',
        'title'     => 'Trainings Attended (As Participant)',
        'icon'      => 'bi-journal-check',
        'itemLabel' => 'Training',
        'addLabel'  => 'Add Another Training',
        'note'      => $renewal
            ? 'Filled in from your last application. List the trainings and seminars you attended as a participant <strong>since the last issuance of your accreditation</strong>, starting from the latest, and upload their certificates below.'
            : 'List trainings attended as a participant starting from the most recent. Under standard requirements, your mandatory <strong>40-hour BOSH/COSH certificate</strong> must be uploaded.',
        'fields'    => [
            ['name' => 'title',        'label' => 'Title of Training', 'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'conducted_by', 'label' => 'Conducted By',      'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'date_from',    'label' => 'Date From',         'type' => 'date',   'col' => 'col-md-6'],
            ['name' => 'date_to',      'label' => 'Date To',           'type' => 'date',   'col' => 'col-md-6'],
            ['name' => 'no_of_hours',  'label' => 'No. of Hours',      'type' => 'number', 'col' => 'col-md-6', 'min' => 0, 'step' => '0.5'],
            ['name' => 'venue',        'label' => 'Venue',             'type' => 'text',   'col' => 'col-md-6'],
        ],
    ])

    {{-- ── Step 7: Lectures conducted ───────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step {{ $step(7) }} &mdash; OSH Lectures / Seminars / Trainings Conducted
        @if($renewal)<span class="prac-step-sub">(since the last issuance of your accreditation)</span>@endif
    </p>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'lectures',
        'title'     => 'Lectures Conducted (As Resource Speaker)',
        'icon'      => 'bi-megaphone-fill',
        'itemLabel' => 'Lecture',
        'addLabel'  => 'Add Another Lecture',
        'note'      => $renewal
            ? 'Filled in from your last application. Lectures or modules you presented as Resource Speaker <strong>since the last issuance of your accreditation</strong>. Write <strong>N.A.</strong> if none.'
            : 'Details of lectures or modules presented as Resource Speaker. Write <strong>N.A.</strong> if none.',
        'fields'    => [
            ['name' => 'topic',         'label' => 'Topic / Title of Lecture', 'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'conducted_for', 'label' => 'Conducted By / For',       'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'date_from',     'label' => 'Date From',                'type' => 'date',   'col' => 'col-md-6'],
            ['name' => 'date_to',       'label' => 'Date To',                  'type' => 'date',   'col' => 'col-md-6'],
            ['name' => 'no_of_hours',   'label' => 'No. of Hours',             'type' => 'number', 'col' => 'col-md-6', 'min' => 0, 'step' => '0.5'],
            ['name' => 'venue',         'label' => 'Venue',                    'type' => 'text',   'col' => 'col-md-6'],
        ],
    ])

    {{-- ── Step 8: Skills ───────────────────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step {{ $step(8) }} &mdash; OSH Skills / Expertise / Specialization</p>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'skills',
        'title'     => 'Skills & Areas of Expertise',
        'icon'      => 'bi-tools',
        'itemLabel' => 'Skill',
        'addLabel'  => 'Add Another Skill',
        'fields'    => [
            ['name' => 'trade_occupation',    'label' => 'Trade / Occupation',   'type' => 'text',     'col' => 'col-md-6', 'required' => true],
            ['name' => 'field_of_expertise',  'label' => 'Field of Expertise',   'type' => 'text',     'col' => 'col-md-6', 'required' => true],
            ['name' => 'years_of_experience', 'label' => 'Years of Experience',  'type' => 'number',   'col' => 'col-md-3', 'min' => 0, 'max' => 80],
            ['name' => 'description',         'label' => 'Brief Description',    'type' => 'textarea', 'col' => 'col-md-9', 'rows' => 2],
        ],
    ])

    {{-- ── Step 9: Awards ───────────────────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step {{ $step(9) }} &mdash; OSH Awards / Achievements / Recognitions</p>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'awards',
        'title'     => 'Awards & Recognitions Received',
        'icon'      => 'bi-award-fill',
        'itemLabel' => 'Award',
        'addLabel'  => 'Add Another Award',
        // The AF-PCR-A2 adds a "Brief Description" column the AF-PCN-A1 lacks.
        'fields'    => array_merge([
            ['name' => 'title',       'label' => 'Title of Award / Recognition', 'type' => 'text', 'col' => 'col-md-5'],
            ['name' => 'issued_by',   'label' => 'Issued By',                    'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'date_issued', 'label' => 'Date Issued',                  'type' => 'date', 'col' => 'col-md-3'],
        ], $renewal ? [
            ['name' => 'description', 'label' => 'Brief Description',           'type' => 'textarea', 'col' => 'col-12', 'rows' => 2, 'attrs' => 'maxlength="1000"'],
        ] : []),
    ])

    {{-- ── Step 10: Examinations ────────────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step {{ $step(10) }} &mdash; OSH Examinations / Eligibilities Passed</p>

    {{-- Steps 10 – 13 are not on the AF-PCR-A2: shown, locked, on a renewal. --}}
    <fieldset class="prac-fieldset" @disabled($renewal)>
    @includeWhen($renewal, 'landing.partials.practitioner.locked_note')

    @include('landing.partials.practitioner.repeater', [
        'pracLocked' => $renewal,
        'key'       => 'examinations',
        'title'     => 'Examinations & Eligibilities',
        'icon'      => 'bi-card-checklist',
        'itemLabel' => 'Examination',
        'addLabel'  => 'Add Another Examination',
        'fields'    => [
            ['name' => 'title',      'label' => 'Title / Details of Examination', 'type' => 'text',   'col' => 'col-md-5'],
            ['name' => 'given_by',   'label' => 'Given By',                       'type' => 'text',   'col' => 'col-md-3'],
            ['name' => 'year_taken', 'label' => 'Year Taken',                     'type' => 'number', 'col' => 'col-md-2', 'min' => 1950, 'max' => 2100, 'placeholder' => 'e.g. 2022'],
            ['name' => 'rating',     'label' => 'Rating / Score',                 'type' => 'text',   'col' => 'col-md-2', 'placeholder' => '(if any)'],
        ],
    ])

    {{-- ── Step 11: Memberships ─────────────────────────────────────────── --}}
    </fieldset>

    {{-- ── Step 11: Memberships ─────────────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step {{ $step(11) }} &mdash; Memberships / Affiliations Related to OSH</p>

    <fieldset class="prac-fieldset" @disabled($renewal)>
    @includeWhen($renewal, 'landing.partials.practitioner.locked_note')

    @include('landing.partials.practitioner.repeater', [
        'pracLocked' => $renewal,
        'key'       => 'memberships',
        'title'     => 'Memberships & Affiliations',
        'icon'      => 'bi-people-fill',
        'itemLabel' => 'Membership',
        'addLabel'  => 'Add Another Membership',
        'fields'    => [
            ['name' => 'organization', 'label' => 'Organization / Institution / Agency', 'type' => 'text', 'col' => 'col-md-5'],
            ['name' => 'designation',  'label' => 'Designation / Position',              'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'validity',     'label' => 'Validity',                            'type' => 'text', 'col' => 'col-md-3', 'placeholder' => 'e.g. 2025'],
        ],
    ])
    </fieldset>

    {{-- ── Step 12: Character references ────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step {{ $step(12) }} &mdash; Character References</p>

    <fieldset class="prac-fieldset" @disabled($renewal)>
    @includeWhen($renewal, 'landing.partials.practitioner.locked_note')

    @include('landing.partials.practitioner.repeater', [
        'pracLocked' => $renewal,
        'key'       => 'references',
        'title'     => 'Character References',
        'icon'      => 'bi-person-check-fill',
        'itemLabel' => 'Reference',
        'addLabel'  => 'Add Another Reference',
        'initial'   => 3,
        'min'       => 3,
        'note'      => 'Give <strong>at least three (3)</strong> character references. All three are required.',
        'fields'    => [
            ['name' => 'full_name',       'label' => 'Full Name',           'type' => 'text', 'col' => 'col-md-6', 'required' => true],
            ['name' => 'designation',     'label' => 'Designation / Position', 'type' => 'text', 'col' => 'col-md-6', 'required' => true],
            ['name' => 'company_address', 'label' => 'Company / Address',   'type' => 'text', 'col' => 'col-md-8', 'required' => true],
            ['name' => 'contact_number',  'label' => 'Contact Number',      'type' => 'tel',  'col' => 'col-md-4', 'required' => true, 'placeholder' => '09XXXXXXXXX'],
        ],
    ])
    </fieldset>

    {{-- ── Step 13: Declarations ────────────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step {{ $step(13) }} &mdash; Declarations</p>

    <fieldset class="prac-fieldset" @disabled($renewal)>
    @includeWhen($renewal, 'landing.partials.practitioner.locked_note')

    <div class="prac-card border rounded-3 bg-white shadow-sm p-3 mb-4"
         data-prac-summary-group="Declarations">
        <div class="prac-note mb-3">
            <i class="bi bi-info-circle-fill me-1"></i>
            <span>Answer every question truthfully. A "Yes" answer does not automatically disqualify an application, but a false declaration does.</span>
        </div>
        {{-- Each group's details box opens when one of its questions is answered
             Yes (practitioner-form.js), as the paper form's "give details" line. --}}
        <div class="row g-2">
            @foreach([
                [
                    'questions' => [
                        ['name' => 'has_pending_admin_case',    'label' => 'a) Do you have any pending administrative case?'],
                        ['name' => 'has_pending_criminal_case', 'label' => 'b) Do you have any pending criminal case?'],
                    ],
                    'details' => ['name' => 'pending_case_details', 'label' => 'Details of the pending case', 'help' => 'If you have any, give details of the offense.',
                                  'placeholder' => 'The offense, where the case is filed, its case number and status'],
                ],
                [
                    'questions' => [['name' => 'has_crime_conviction', 'label' => 'c) Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation by any court or tribunal?']],
                    'details'   => ['name' => 'crime_conviction_details', 'label' => 'Details of the conviction', 'help' => 'If yes, give details.',
                                    'placeholder' => 'The offense, the court or tribunal, and the date of the decision'],
                ],
                [
                    'questions' => [['name' => 'has_admin_offense_conviction', 'label' => 'd) Have you ever been convicted of any administrative offense?']],
                    'details'   => ['name' => 'admin_offense_details', 'label' => 'Details of the administrative offense', 'help' => 'If your answer is "Yes", give details of the offense.',
                                    'placeholder' => 'The offense, the office that decided it, the date and the penalty'],
                ],
                [
                    'questions' => [['name' => 'has_forced_separation', 'label' => 'e) Have you ever been retired, forced to resign or dropped from employment in the public and private sector?']],
                    'details'   => ['name' => 'forced_separation_reasons', 'label' => 'Reasons', 'help' => 'If yes, give reasons.',
                                    'placeholder' => 'The employer, the date and the reason for leaving'],
                ],
            ] as $declGroup)
                @php
                    $declNames = array_column($declGroup['questions'], 'name');
                    $declShown = collect($declNames)->contains(
                        fn ($q) => in_array($pracValues['declarations'][$q] ?? null, [true, 1, '1'], true)
                    );
                @endphp
                @foreach($declGroup['questions'] as $f)
                    @include('landing.partials.practitioner.field', [
                        'f'        => $f + ['type' => 'yesno', 'col' => 'col-12', 'required' => true],
                        'group'    => 'practitioner[declarations]',
                        'idPrefix' => 'prac_decl',
                        'pracLocked' => $renewal,
                    ])
                @endforeach
                <div class="col-12 prac-decl-details" data-prac-decl-details="{{ implode(' ', $declNames) }}" @unless($declShown) hidden @endunless>
                    @include('landing.partials.practitioner.field', [
                        'f'        => $declGroup['details'] + ['type' => 'textarea', 'col' => 'w-100', 'rows' => 2, 'required' => true, 'attrs' => 'maxlength="1000"'],
                        'group'    => 'practitioner[declarations]',
                        'idPrefix' => 'prac_decl',
                        'pracLocked' => $renewal,
                    ])
                </div>
            @endforeach
        </div>
    </div>
    </fieldset>

    {{-- ── Step 14: Documentary requirements ────────────────────────────── --}}
    @if($renewal)
        <p class="form-section-title mt-4">Step {{ $step(14) }} &mdash; Submission of Documentary Requirements</p>
        @include('landing.partials.practitioner.renewal_documents')
    @endif

    @unless($correcting || $renewal)
    <p class="form-section-title mt-4">Step 14 &mdash; Submission of Documentary Requirements</p>

    <div class="row g-3">
        <div class="col-12">
            <div class="alert alert-info rounded-3 prac-upload-info">
                <h6 class="fw-bold mb-2"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Document Upload Instructions</h6>
                <p class="mb-0 prac-upload-info-text">
                    Upload each required file in <strong>PDF format only</strong> (maximum <strong>15 MB</strong> per file).
                    Scans must be clear and fully readable.
                </p>
            </div>
        </div>

        {{-- One checklist per type; practitioner-form.js shows and enables
             only the selected type's (the other's required inputs would
             otherwise block the Review button). Which uploads are required
             is enforced again by PractitionerApplicationService::REQUIRED_DOCUMENTS. --}}
        @foreach([
            [
                'type'  => $typeIds['practitioner'],
                'title' => 'Practitioner Documentary Requirements',
                'docs'  => [
                    ['code' => 'PRAC_01', 'title' => 'Original Certificate of Employment', 'label' => 'Signed by the Employer or HR.', 'required' => true],
                    ['code' => 'PRAC_02', 'title' => 'Actual Duties and Responsibilities', 'label' => 'Signed by your immediate Supervisor.', 'required' => true],
                    ['code' => 'PRAC_03', 'title' => 'Certificate of Employment (Previous Employer)', 'label' => 'If applicable.', 'required' => false],
                    ['code' => 'PRAC_04', 'title' => 'Certificate of Completion — 40-hour Basic OSH', 'label' => 'Bureau-prescribed 40-hour Basic OSH Training (BOSH/COSH).', 'required' => true],
                    ['code' => 'PRAC_05', 'title' => 'Certificates of Attendance', 'label' => 'Other OSH-related trainings and seminars attended.', 'required' => true],
                    ['code' => 'PRAC_06', 'title' => 'College Diploma or Transcript of Records', 'label' => 'Photocopy of diploma or TOR.', 'required' => true],
                    ['code' => 'PRAC_07', 'title' => 'Proof of OSH-Related Accomplishments', 'label' => 'Accomplishments or participation in OSH activities/programs.', 'required' => true],
                ],
            ],
            [
                'type'  => $typeIds['consultant'],
                'title' => 'Consultant Documentary Requirements',
                'docs'  => [
                    ['code' => 'CONS_01', 'title' => 'Certificate of Employment or Consultancy Contract', 'label' => 'From your current employer or client.', 'required' => true],
                    ['code' => 'CONS_02', 'title' => 'List of OSH Consultancy Projects', 'label' => 'With client validation.', 'required' => true],
                    ['code' => 'CONS_03', 'title' => 'Certificate of Completion of Advanced OSH Courses', 'label' => 'Advanced OSH training courses completed.', 'required' => true],
                    ['code' => 'CONS_04', 'title' => 'Proof of at least 5 Years OSH Experience', 'label' => 'In a supervisory capacity.', 'required' => true],
                    ['code' => 'CONS_05', 'title' => 'College Diploma or Transcript of Records', 'label' => 'Photocopy of diploma or TOR.', 'required' => true],
                    ['code' => 'CONS_06', 'title' => 'Professional License (PRC ID)', 'label' => 'If applicable.', 'required' => false],
                ],
            ],
        ] as $checklist)
        <div class="col-12" @if($pracType === null) data-prac-for="{{ $checklist['type'] }}" @endif>
            {{-- .doc-type-section opts this group into the shared collapse +
                 "n/m attached" badge already built for the FATPro checklist. --}}
            <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                <h6 class="fw-bold mb-3 prac-doc-heading">
                    <span class="badge me-2 prac-doc-badge">1</span>{{ $checklist['title'] }}
                </h6>
                <div class="row g-3">
                    @foreach($checklist['docs'] as $f)
                        <div class="col-md-6 mb-2 prac-field" data-label="{{ $f['title'] }}" data-prac-document>
                            <label class="form-label fw-bold mb-0 prac-label" for="doc_{{ $f['code'] }}">
                                {{ $f['title'] }} @if($f['required']) <span class="text-danger">*</span> @endif
                            </label>
                            <div class="form-text mt-0 mb-2 prac-help">{{ $f['label'] }}</div>
                            <div class="file-upload-wrapper mt-1">
                                <input class="real-file-input visually-hidden" type="file"
                                       name="documents[{{ $f['code'] }}]" id="doc_{{ $f['code'] }}"
                                       accept=".pdf" data-allowed-ext="pdf" @if($f['required']) required @endif>
                                <div class="d-flex align-items-center gap-2">
                                    <label for="doc_{{ $f['code'] }}" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                                        <i class="bi bi-cloud-upload me-1"></i> Choose File
                                    </label>
                                    <span class="file-name-text text-muted text-truncate prac-file-name">No file chosen</span>
                                </div>
                                <div class="invalid-feedback file-invalid-feedback prac-file-feedback">Please select a valid PDF file.</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endunless

</div>
