<?php

/**
 * Shared option lists for accreditation forms.
 *
 * Kept in config rather than hardcoded in the blade so the registration form,
 * the eventual FormRequest (Rule::in) and the admin evaluation screens all read
 * the same list — a select and its validation rule drifting apart is the usual
 * way these forms start rejecting valid input.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Accreditation type IDs
    |--------------------------------------------------------------------------
    | Mirrors database/seeders/AccreditationTypeSeeder.php insertion order.
    */
    'types' => [
        'practitioner' => 1,
        'consultant'   => 2,
        'wem'          => 3,
        'cheto'        => 4,
        'sto'          => 5,
        'sco'          => 6,
        'fatpro'       => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Accreditation types open for online registration
    |--------------------------------------------------------------------------
    | The registration form disables the other options and the Practitioner
    | branch blocks its own submit button, but both of those are client-side.
    | RegistrationController rejects anything not listed here, so a hand-crafted
    | POST cannot open an application against a type the system cannot process.
    |
    */
    'open_for_registration' => [
        1, // OSH Practitioners
        2, // OSH Consultant — same form and process as Practitioners
        7, // First Aid Training Providers
    ],

    /*
    |--------------------------------------------------------------------------
    | Types handled end to end by the Accreditation division
    |--------------------------------------------------------------------------
    | Applications of these types are received, assigned and evaluated by the
    | Accreditation division instead of HCD. HCD's application lists leave
    | them out, and their admin links point at the Accreditation portal.
    | type id => label shown on the Accreditation division's pages.
    */
    'accreditation_division_types' => [
        1 => 'OSH Practitioner',
        2 => 'OSH Consultant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Interview time slots (Accreditation division)
    |--------------------------------------------------------------------------
    | The preset times offered on "Manage Interview Slots"; admins can add
    | custom times beside them. [start, end] in 24-hour H:i.
    */
    'interview_time_slots' => [
        ['09:00', '10:30'],
        ['10:30', '12:00'],
        ['13:00', '14:30'],
        ['14:30', '16:00'],
    ],

    /** Pre-filled venue for face-to-face interviews. */
    'interview_default_venue' => 'Occupational Safety and Health Center',

    /*
    |--------------------------------------------------------------------------
    | Accreditation fee (PHP), by type id
    |--------------------------------------------------------------------------
    | Shown in the payment instructions email and on the tracking page. Types
    | not listed fall back to 'default'.
    */
    /*
    |--------------------------------------------------------------------------
    | Recommendation form defaults (Accreditation division)
    |--------------------------------------------------------------------------
    | Pre-filled on the OSH Practitioner recommendation form, editable there.
    | FATPro's (HCD) defaults are in admin/hcd/show_application_info.
    */
    'recommendation_defaults' => [
        'from'           => 'Accreditation Division',
        'to'             => 'Office of the Executive Director (OED)',
        'recommended_by' => '',
        'approved_by'    => 'ENGR. JOSE MARIA S. BATINO',
    ],

    /*
    |--------------------------------------------------------------------------
    | Accreditation numbers (Accreditation division), by type id
    |--------------------------------------------------------------------------
    | {prefix}-{date}[-{infix}]-{sequence}, as on the OSHC certificates. The
    | sequence carries on from the last number issued before ARMS, and each
    | type counts on its own:
    |   OSH Practitioner  1033-YYMMDD-1951        (date ymd, at least 3 digits)
    |   OSH Consultant    1030-MMDDYY-OSHC-0519   (date mdy, 4 digits)
    | date_format is a PHP date() format; pad is the sequence's minimum digits.
    */
    'accreditation_numbers' => [
        1 => ['prefix' => '1033', 'date_format' => 'ymd', 'pad' => 3, 'last_issued' => 1950], // OSH Practitioner
        2 => ['prefix' => '1030', 'date_format' => 'mdy', 'infix' => 'OSHC', 'pad' => 4, 'last_issued' => 518], // OSH Consultant
    ],

    /** Years an Accreditation division accreditation is valid. */
    'accreditation_validity_years' => 3,

    /*
    |--------------------------------------------------------------------------
    | Certificate of Accreditation wording, by type id
    |--------------------------------------------------------------------------
    | Fills the OSHC templates, front and back:
    |   OSH Practitioner  admin/accreditation/practitioner/certificate_pdf
    |   OSH Consultant    admin/accreditation/practitioner/certificate_consultant_pdf
    | type: the certificate's "OSH Practitioner." / "OSH Consultant." on the back.
    */
    'certificate_text' => [
        1 => [
            'type'   => 'OSH Practitioner',
            'body'   => 'is accredited by this Department as an Occupational Safety and Health Practitioner pursuant to the Occupational Safety and Health Standards and is authorized to practice as such in the field of occupational safety and health in the Philippines within the validity period.',
            'footer' => '(Accreditation is valid per requirements set forth in Rules 1030 and 1974 of the Occupational Safety and Health Standards as amended.)',
        ],
        2 => [
            'type'   => 'OSH Consultant',
            'body'   => 'is accredited by this Department as Occupational Safety and Health Consultant pursuant to the Occupational Safety and Health Standards and is authorized to practice as such in the field of occupational safety and health in the Philippines within the validity period.',
            'footer' => '(Accreditation is valid per requirements set forth in Rules 1030 and 1974 of the Occupational Safety and Health Standards as amended.)',
        ],
    ],

    /*
    | The position under the CERTIFICATION signature on the back of the OSH
    | Practitioner and OSH Consultant certificates. The Generate Certificate
    | pop-up starts with it (editable); the officer's name has no default —
    | it changes, so the admin types it each time.
    */
    'certificate_back_signatory' => [
        'title' => 'OIC-Deputy Executive Director',
    ],

    'payment_amounts' => [
        1         => 450.00, // OSH Practitioner
        2         => 850.00, // OSH Consultant
        7         => 900.00, // First Aid Training Provider
        'default' => 900.00,
    ],

    /*
    |--------------------------------------------------------------------------
    | Personal profile options
    |--------------------------------------------------------------------------
    */
    'sexes' => ['Male', 'Female'],

    'civil_statuses' => ['Single', 'Married', 'Widow/er', 'Separated'],

    'blood_types' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],

    /*
    | PSIC-aligned industry sections, trimmed to the ones OSH practitioners
    | actually report. "Other" reveals a free-text field rather than silently
    | dropping an industry that is not on the list.
    */
    'industries' => [
        'Agriculture, Forestry and Fishing',
        'Mining and Quarrying',
        'Manufacturing',
        'Electricity, Gas, Steam and Air Conditioning Supply',
        'Water Supply, Sewerage and Waste Management',
        'Construction',
        'Wholesale and Retail Trade; Repair of Motor Vehicles',
        'Transportation and Storage',
        'Accommodation and Food Service Activities',
        'Information and Communication',
        'Business Process Outsourcing (BPO)',
        'Financial and Insurance Activities',
        'Real Estate Activities',
        'Professional, Scientific and Technical Activities',
        'Administrative and Support Service Activities',
        'Public Administration and Defense',
        'Education',
        'Human Health and Social Work Activities',
        'Arts, Entertainment and Recreation',
        'Other Service Activities',
        'Other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Workplace character
    |--------------------------------------------------------------------------
    */
    'hazard_levels' => ['Hazardous', 'Non-Hazardous'],

    'regions' => [
        'NCR — National Capital Region',
        'CAR — Cordillera Administrative Region',
        'Region I — Ilocos Region',
        'Region II — Cagayan Valley',
        'Region III — Central Luzon',
        'Region IV-A — CALABARZON',
        'Region IV-B — MIMAROPA',
        'Region V — Bicol Region',
        'Region VI — Western Visayas',
        'Region VII — Central Visayas',
        'Region VIII — Eastern Visayas',
        'Region IX — Zamboanga Peninsula',
        'Region X — Northern Mindanao',
        'Region XI — Davao Region',
        'Region XII — SOCCSKSARGEN',
        'Region XIII — Caraga',
        'BARMM — Bangsamoro Autonomous Region in Muslim Mindanao',
    ],

    /*
    |--------------------------------------------------------------------------
    | Work experience
    |--------------------------------------------------------------------------
    */
    'appointment_statuses' => [
        'Permanent',
        'Contractual',
        'Probationary',
        'Casual',
        'Job Order',
        'Project-Based',
        'Consultant',
        'Part-Time',
        'Self-Employed',
        'Other',
    ],
];
