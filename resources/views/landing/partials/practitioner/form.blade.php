{{--
    OSH Practitioner accreditation — form body (display only).

    Shown when accreditation type 1 (Practitioners) is selected. The core person
    fields (name, sex, date of birth, region, city, city address) are NOT
    repeated here — they already live in #individualFields above and are wired to
    landing.js's IND_REQUIRED list. This partial adds the practitioner-specific
    profile columns, the ten repeatable CV sections, the disclosures and the
    documentary checklist.

    Nothing here is processed yet: practitioner-form.js blocks submission and the
    controller has no practitioner branch. The field names match the schema in
    2026_09_13_000001_create_practitioner_tables.php so wiring it up later is a
    controller change and nothing else.
--}}
@php
    $opt = config('accreditation');
@endphp

<div id="practitionerSections" class="d-none">

    {{-- The requirements notice for this type lives in
         landing.partials.practitioner.notice, rendered beside
         #fatproRequirementsNotice so it is on screen before Step 2. --}}

    {{-- ── Personal profile (continues #individualFields above) ─────────── --}}
    <p class="form-section-title">Step 3 &mdash; Personal Profile <span class="prac-step-sub">(continued)</span></p>

    <div class="row g-3">
        @foreach([
            ['name' => 'civil_status',  'label' => 'Civil Status',  'type' => 'select', 'col' => 'col-md-4', 'required' => true, 'options' => $opt['civil_statuses']],
            ['name' => 'citizenship',   'label' => 'Citizenship',   'type' => 'text',   'col' => 'col-md-4', 'required' => true, 'placeholder' => 'e.g. Filipino'],
            ['name' => 'religion',      'label' => 'Religion',      'type' => 'text',   'col' => 'col-md-4', 'placeholder' => '(optional)'],
            ['name' => 'height_cm',     'label' => 'Height (cm)',   'type' => 'number', 'col' => 'col-md-4', 'min' => 0, 'step' => '0.01', 'placeholder' => 'e.g. 170'],
            ['name' => 'weight_kg',     'label' => 'Weight (kg)',   'type' => 'number', 'col' => 'col-md-4', 'min' => 0, 'step' => '0.01', 'placeholder' => 'e.g. 65'],
            ['name' => 'blood_type',    'label' => 'Blood Type',    'type' => 'select', 'col' => 'col-md-4', 'options' => $opt['blood_types']],
        ] as $f)
            @include('landing.partials.practitioner.field', ['f' => $f, 'group' => 'practitioner[profile]', 'idPrefix' => 'prac_profile'])
        @endforeach
    </div>

    <p class="prac-subheading mt-4">Addresses</p>
    <div class="row g-3">
        @foreach([
            ['name' => 'home_provincial_address', 'label' => 'Home / Provincial Address', 'type' => 'textarea', 'col' => 'col-md-6', 'rows' => 2, 'placeholder' => '(optional)'],
            ['name' => 'business_address',        'label' => 'Business / Company Address', 'type' => 'textarea', 'col' => 'col-md-6', 'rows' => 2, 'help' => 'If corporate-employed.', 'placeholder' => '(optional)'],
        ] as $f)
            @include('landing.partials.practitioner.field', ['f' => $f, 'group' => 'practitioner[profile]', 'idPrefix' => 'prac_profile'])
        @endforeach
    </div>

    <p class="prac-subheading mt-4">Identification &amp; Contact</p>
    <div class="row g-3">
        @foreach([
            ['name' => 'tin_no',              'label' => 'TIN No.',             'type' => 'text', 'col' => 'col-md-4', 'placeholder' => '(optional)'],
            ['name' => 'prc_license_no',      'label' => 'PRC License No.',     'type' => 'text', 'col' => 'col-md-4', 'help' => 'If any.', 'placeholder' => '(optional)'],
            ['name' => 'sss_gsis_no',         'label' => 'SSS / GSIS No.',      'type' => 'text', 'col' => 'col-md-4', 'placeholder' => '(optional)'],
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

        {{-- Revealed only when "Other" is picked above. --}}
        <div class="col-md-6" id="pracIndustryOtherWrap" hidden>
            @include('landing.partials.practitioner.field', [
                'f' => ['name' => 'type_of_industry_other', 'label' => 'Please specify industry', 'type' => 'text', 'col' => 'w-100', 'placeholder' => 'Specify your industry'],
                'group' => 'practitioner[profile]', 'idPrefix' => 'prac_profile',
            ])
        </div>

        @include('landing.partials.practitioner.field', [
            'f' => ['name' => 'photo', 'label' => 'Required 2x2 ID Photo', 'type' => 'file', 'col' => 'col-md-6', 'required' => true,
                    'help' => 'Recent 2x2 ID picture. JPG or PNG, max 15 MB.',
                    'accept' => 'image/jpeg,image/png', 'allowedExt' => 'jpg,jpeg,png'],
            'group' => 'practitioner[profile]', 'idPrefix' => 'prac_profile',
        ])
    </div>

    {{-- ── Workplace character & demographics ───────────────────────────── --}}
    <p class="prac-subheading mt-4">Workplace Character &amp; Demographics</p>
    <div class="row g-3">
        @foreach([
            ['name' => 'hazard_level',        'label' => 'Workplace Hazard Level', 'type' => 'select', 'col' => 'col-md-4', 'options' => $opt['hazard_levels']],
            ['name' => 'employees_male',      'label' => 'Employees — Male',       'type' => 'number', 'col' => 'col-md-4', 'min' => 0, 'attrs' => 'data-workforce-count="1"', 'placeholder' => '0'],
            ['name' => 'employees_female',    'label' => 'Employees — Female',     'type' => 'number', 'col' => 'col-md-4', 'min' => 0, 'attrs' => 'data-workforce-count="1"', 'placeholder' => '0'],
            ['name' => 'total_workforce_size','label' => 'Total Workforce Size',   'type' => 'number', 'col' => 'col-md-4', 'min' => 0, 'help' => 'Computed automatically from the counts above.', 'attrs' => 'data-workforce-total="1" readonly'],
            ['name' => 'psic_code',           'label' => 'PSIC Code',              'type' => 'text',   'col' => 'col-md-4', 'placeholder' => '(optional)'],
            ['name' => 'region_of_employment','label' => 'Region of Employment',   'type' => 'select', 'col' => 'col-md-4', 'required' => true, 'options' => $opt['regions']],
            ['name' => 'geo_code',            'label' => 'GEO Code',               'type' => 'text',   'col' => 'col-md-4', 'placeholder' => '(optional)'],
            ['name' => 'zip_code',            'label' => 'Zip Code',               'type' => 'text',   'col' => 'col-md-4', 'placeholder' => '(optional)'],
        ] as $f)
            @include('landing.partials.practitioner.field', ['f' => $f, 'group' => 'practitioner[workplace]', 'idPrefix' => 'prac_workplace'])
        @endforeach
    </div>

    {{-- ── Step 4: Educational attainment ───────────────────────────────── --}}
    <p class="form-section-title mt-4">Step 4 &mdash; Educational Attainment</p>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'education',
        'title'     => 'Degrees / Units Earned',
        'icon'      => 'bi-mortarboard-fill',
        'itemLabel' => 'Degree',
        'addLabel'  => 'Add Another Degree',
        'fields'    => [
            ['name' => 'degree',              'label' => 'Degree / Units Earned',            'type' => 'text',     'col' => 'col-md-6', 'required' => true, 'placeholder' => 'e.g. BS Industrial Engineering'],
            ['name' => 'school_name_address', 'label' => 'School Name & Address',            'type' => 'text',     'col' => 'col-md-6', 'required' => true, 'help' => 'Last attended.'],
            ['name' => 'date_from',           'label' => 'Inclusive Dates — From',           'type' => 'date',     'col' => 'col-md-3'],
            ['name' => 'date_to',             'label' => 'Inclusive Dates — To',             'type' => 'date',     'col' => 'col-md-3'],
            ['name' => 'awards_honors',       'label' => 'Awards / Honors Received',         'type' => 'text',     'col' => 'col-md-6', 'placeholder' => '(optional)'],
        ],
    ])

    @include('landing.partials.practitioner.repeater', [
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

    {{-- ── Step 5: Work experience ──────────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step 5 &mdash; Work Experience</p>

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
        'note'      => 'List your work experience from the <strong>most recent to the present</strong>.',
        'fields'    => [
            ['name' => 'company',            'label' => 'Company / Organization',   'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'designation',        'label' => 'Designation / Position',   'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'date_from',          'label' => 'Inclusive Dates — From',   'type' => 'date',   'col' => 'col-md-3', 'attrs' => 'data-service-from="1"'],
            ['name' => 'date_to',            'label' => 'Inclusive Dates — To',     'type' => 'date',   'col' => 'col-md-3', 'help' => 'Leave blank if present.', 'attrs' => 'data-service-to="1"'],
            ['name' => 'length_of_service',  'label' => 'Length of Service',        'type' => 'text',   'col' => 'col-md-3', 'help' => 'Computed from the dates.', 'attrs' => 'data-service-length="1" readonly'],
            ['name' => 'appointment_status', 'label' => 'Status of Appointment',    'type' => 'select', 'col' => 'col-md-3', 'options' => config('accreditation.appointment_statuses')],
        ],
    ])

    {{-- ── Step 6: Trainings attended ───────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step 6 &mdash; OSH-Related Trainings / Seminars Attended</p>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'trainings',
        'title'     => 'Trainings Attended (As Participant)',
        'icon'      => 'bi-journal-check',
        'itemLabel' => 'Training',
        'addLabel'  => 'Add Another Training',
        'note'      => 'List trainings attended as a participant starting from the most recent. Under standard requirements, your mandatory <strong>40-hour BOSH/COSH certificate</strong> must be uploaded.',
        'fields'    => [
            ['name' => 'title',        'label' => 'Title of Training', 'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'conducted_by', 'label' => 'Conducted By',      'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'date_from',    'label' => 'Date From',         'type' => 'date',   'col' => 'col-md-3'],
            ['name' => 'date_to',      'label' => 'Date To',           'type' => 'date',   'col' => 'col-md-3'],
            ['name' => 'no_of_hours',  'label' => 'No. of Hours',      'type' => 'number', 'col' => 'col-md-3', 'min' => 0, 'step' => '0.5'],
            ['name' => 'venue',        'label' => 'Venue',             'type' => 'text',   'col' => 'col-md-3'],
        ],
    ])

    {{-- ── Step 7: Lectures conducted ───────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step 7 &mdash; OSH Lectures / Seminars / Trainings Conducted</p>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'lectures',
        'title'     => 'Lectures Conducted (As Resource Speaker)',
        'icon'      => 'bi-megaphone-fill',
        'itemLabel' => 'Lecture',
        'addLabel'  => 'Add Another Lecture',
        'note'      => 'Details of lectures or modules presented as Resource Speaker. Write <strong>N.A.</strong> if none.',
        'fields'    => [
            ['name' => 'topic',         'label' => 'Topic / Title of Lecture', 'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'conducted_for', 'label' => 'Conducted By / For',       'type' => 'text',   'col' => 'col-md-6', 'required' => true],
            ['name' => 'date_from',     'label' => 'Date From',                'type' => 'date',   'col' => 'col-md-3'],
            ['name' => 'date_to',       'label' => 'Date To',                  'type' => 'date',   'col' => 'col-md-3'],
            ['name' => 'no_of_hours',   'label' => 'No. of Hours',             'type' => 'number', 'col' => 'col-md-3', 'min' => 0, 'step' => '0.5'],
            ['name' => 'venue',         'label' => 'Venue',                    'type' => 'text',   'col' => 'col-md-3'],
        ],
    ])

    {{-- ── Step 8: Skills ───────────────────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step 8 &mdash; OSH Skills / Expertise / Specialization</p>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'skills',
        'title'     => 'Skills &amp; Areas of Expertise',
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
    <p class="form-section-title mt-4">Step 9 &mdash; OSH Awards / Achievements / Recognitions</p>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'awards',
        'title'     => 'Awards &amp; Recognitions Received',
        'icon'      => 'bi-award-fill',
        'itemLabel' => 'Award',
        'addLabel'  => 'Add Another Award',
        'fields'    => [
            ['name' => 'title',       'label' => 'Title of Award / Recognition', 'type' => 'text', 'col' => 'col-md-5'],
            ['name' => 'issued_by',   'label' => 'Issued By',                    'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'date_issued', 'label' => 'Date Issued',                  'type' => 'date', 'col' => 'col-md-3'],
        ],
    ])

    {{-- ── Step 10: Examinations ────────────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step 10 &mdash; OSH Examinations / Eligibilities Passed</p>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'examinations',
        'title'     => 'Examinations &amp; Eligibilities',
        'icon'      => 'bi-card-checklist',
        'itemLabel' => 'Examination',
        'addLabel'  => 'Add Another Examination',
        'fields'    => [
            ['name' => 'title',      'label' => 'Title / Details of Examination', 'type' => 'text',   'col' => 'col-md-5'],
            ['name' => 'given_by',   'label' => 'Given By',                       'type' => 'text',   'col' => 'col-md-3'],
            ['name' => 'year_taken', 'label' => 'Year Taken',                     'type' => 'number', 'col' => 'col-md-2', 'min' => 1950, 'max' => 2100, 'placeholder' => 'e.g. 2022'],
            ['name' => 'rating',     'label' => 'Rating / Score',                 'type' => 'text',   'col' => 'col-md-2', 'help' => 'If any.'],
        ],
    ])

    {{-- ── Step 11: Memberships ─────────────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step 11 &mdash; Memberships / Affiliations Related to OSH</p>

    @include('landing.partials.practitioner.repeater', [
        'key'       => 'memberships',
        'title'     => 'Memberships &amp; Affiliations',
        'icon'      => 'bi-people-fill',
        'itemLabel' => 'Membership',
        'addLabel'  => 'Add Another Membership',
        'fields'    => [
            ['name' => 'organization', 'label' => 'Organization / Institution / Agency', 'type' => 'text', 'col' => 'col-md-5'],
            ['name' => 'designation',  'label' => 'Designation / Position',              'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'validity',     'label' => 'Validity',                            'type' => 'text', 'col' => 'col-md-3', 'placeholder' => 'e.g. 2025'],
        ],
    ])

    {{-- ── Step 12: Character references ────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step 12 &mdash; Character References</p>

    @include('landing.partials.practitioner.repeater', [
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

    {{-- ── Step 13: Declarations ────────────────────────────────────────── --}}
    <p class="form-section-title mt-4">Step 13 &mdash; Declarations</p>

    <div class="prac-card border rounded-3 bg-white shadow-sm p-3 mb-4"
         data-prac-summary-group="Declarations">
        <div class="prac-note mb-3">
            <i class="bi bi-info-circle-fill me-1"></i>
            <span>Answer every question truthfully. A "Yes" answer does not automatically disqualify an application, but a false declaration does.</span>
        </div>
        <div class="row g-2">
            @foreach([
                ['name' => 'has_pending_admin_case',        'label' => 'a) Do you have any pending administrative case?'],
                ['name' => 'has_pending_criminal_case',     'label' => 'b) Do you have any pending criminal case?'],
                ['name' => 'has_crime_conviction',          'label' => 'c) Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation by any court or tribunal?'],
                ['name' => 'has_admin_offense_conviction',  'label' => 'd) Have you ever been convicted of any administrative offense?'],
                ['name' => 'has_forced_separation',         'label' => 'e) Have you ever been retired, forced to resign or dropped from employment in the public and private sector?'],
            ] as $f)
                @include('landing.partials.practitioner.field', [
                    'f'        => $f + ['type' => 'yesno', 'col' => 'col-12', 'required' => true],
                    'group'    => 'practitioner[declarations]',
                    'idPrefix' => 'prac_decl',
                ])
            @endforeach
        </div>
    </div>

    {{-- ── Step 14: Documentary requirements ────────────────────────────── --}}
    <p class="form-section-title mt-4">Step 14 &mdash; Submission of Documentary Requirements</p>

    <div class="row g-3">
        <div class="col-12">
            <div class="alert alert-info rounded-3" style="background:rgba(46,111,216,.08);border:1px solid rgba(46,111,216,.2);color:var(--blue-deep);">
                <h6 class="fw-bold mb-2"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Document Upload Instructions</h6>
                <p class="mb-0" style="font-size:.85rem;">
                    Upload each required file in <strong>PDF format only</strong> (maximum <strong>15 MB</strong> per file).
                    Scans must be clear and fully readable.
                </p>
            </div>
        </div>

        <div class="col-12">
            {{-- .doc-type-section opts this group into the shared collapse +
                 "n/m attached" badge already built for the FATPro checklist. --}}
            <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                <h6 class="fw-bold mb-3" style="color:#0b3d91;">
                    <span class="badge me-2" style="background:#0b3d91;">1</span>Practitioner Documentary Requirements
                </h6>
                <div class="row g-3">
                    @foreach([
                        ['code' => 'PRAC_01', 'title' => 'Original Certificate of Employment', 'label' => 'Signed by the Employer or HR.', 'required' => true],
                        ['code' => 'PRAC_02', 'title' => 'Actual Duties and Responsibilities', 'label' => 'Signed by your immediate Supervisor.', 'required' => true],
                        ['code' => 'PRAC_03', 'title' => 'Certificate of Employment (Previous Employer)', 'label' => 'If applicable.', 'required' => false],
                        ['code' => 'PRAC_04', 'title' => 'Certificate of Completion — 40-hour Basic OSH', 'label' => 'Bureau-prescribed 40-hour Basic OSH Training (BOSH/COSH).', 'required' => true],
                        ['code' => 'PRAC_05', 'title' => 'Certificates of Attendance', 'label' => 'Other OSH-related trainings and seminars attended.', 'required' => true],
                        ['code' => 'PRAC_06', 'title' => 'College Diploma or Transcript of Records', 'label' => 'Photocopy of diploma or TOR.', 'required' => true],
                        ['code' => 'PRAC_07', 'title' => 'Proof of OSH-Related Accomplishments', 'label' => 'Accomplishments or participation in OSH activities/programs.', 'required' => true],
                    ] as $f)
                        <div class="col-md-6 mb-2 prac-field" data-label="{{ $f['title'] }}">
                            <label class="form-label fw-bold mb-0" style="font-size:.88rem;" for="doc_{{ $f['code'] }}">
                                {{ $f['title'] }} @if($f['required']) <span class="text-danger">*</span> @endif
                            </label>
                            <div class="form-text mt-0 mb-2" style="font-size:.75rem;line-height:1.2;color:#6c757d;">{{ $f['label'] }}</div>
                            <div class="file-upload-wrapper mt-1">
                                <input class="real-file-input visually-hidden" type="file"
                                       name="documents[{{ $f['code'] }}]" id="doc_{{ $f['code'] }}"
                                       accept=".pdf" data-allowed-ext="pdf" @if($f['required']) required @endif>
                                <div class="d-flex align-items-center gap-2">
                                    <label for="doc_{{ $f['code'] }}" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                                        <i class="bi bi-cloud-upload me-1"></i> Choose File
                                    </label>
                                    <span class="file-name-text text-muted text-truncate" style="font-size:.8rem;max-width:200px;">No file chosen</span>
                                </div>
                                <div class="invalid-feedback file-invalid-feedback" style="font-size:.8rem;margin-top:4px;">Please select a valid PDF file.</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Submission is intentionally not wired up yet. --}}
    <div id="pracSubmissionNotice" class="prac-blocked-notice mt-4">
        <i class="bi bi-cone-striped me-2"></i>
        <span>
            <strong>Online submission for OSH Practitioner accreditation is not yet open.</strong>
            You can fill in and review every section here, but the application cannot be submitted yet.
        </span>
    </div>

</div>
