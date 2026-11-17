<?php

namespace App\Models;

/** AF-PCN-A1 section 2 — professional licenses. */
class PractitionerProfessionalLicense extends PractitionerRecord
{
    protected $table = 'practitioner_professional_licenses';

    protected $casts = [
        'date_issued' => 'date',
    ];
}
