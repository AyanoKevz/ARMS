<?php

namespace App\Models;

/** AF-PCN-A1 section 7 — awards / recognitions. */
class PractitionerAward extends PractitionerRecord
{
    protected $table = 'practitioner_awards';

    protected $casts = [
        'date_issued' => 'date',
    ];
}
