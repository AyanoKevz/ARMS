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
    | Add 1 (practitioner) once the controller can store the CV sections.
    */
    'open_for_registration' => [
        7, // First Aid Training Providers
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
