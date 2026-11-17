<?php

namespace App\Models;

/** AF-PCN-A1 section 5 — OSH lectures conducted. */
class PractitionerLecture extends PractitionerRecord
{
    protected $table = 'practitioner_lectures';

    protected $casts = [
        'date_from' => 'date',
        'date_to'   => 'date',
    ];
}
