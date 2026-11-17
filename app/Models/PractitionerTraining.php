<?php

namespace App\Models;

/** AF-PCN-A1 section 4 — OSH trainings attended. */
class PractitionerTraining extends PractitionerRecord
{
    protected $table = 'practitioner_trainings';

    protected $casts = [
        'date_from' => 'date',
        'date_to'   => 'date',
    ];
}
