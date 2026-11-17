<?php

namespace App\Models;

/** AF-PCN-A1 section 3 — work experience. */
class PractitionerWorkExperience extends PractitionerRecord
{
    protected $table = 'practitioner_work_experiences';

    protected $casts = [
        'date_from' => 'date',
        'date_to'   => 'date',
    ];
}
