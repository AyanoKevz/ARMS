<?php

namespace App\Models;

/** AF-PCN-A1 section 2 — educational attainment. */
class PractitionerEducation extends PractitionerRecord
{
    protected $table = 'practitioner_educations';

    protected $casts = [
        'date_from' => 'date',
        'date_to'   => 'date',
    ];
}
