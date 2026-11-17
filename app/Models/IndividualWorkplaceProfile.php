<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Workplace character and demographics of an individual applicant (1:1). */
class IndividualWorkplaceProfile extends Model
{
    protected $fillable = [
        'individual_profile_id',
        'hazard_level',
        'employees_male',
        'employees_female',
        'total_workforce_size',
        'psic_code',
        'region_of_employment',
        'geo_code',
        'zip_code',
    ];

    public function individualProfile()
    {
        return $this->belongsTo(IndividualProfile::class);
    }
}
