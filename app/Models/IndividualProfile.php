<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndividualProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'sex',
        'birthday',
        'region',
        'city',
        'address',
        'photo_path',
        // AF-PCN-A1 profile columns (2026_09_13_000001_create_practitioner_tables)
        'civil_status',
        'citizenship',
        'height_cm',
        'weight_kg',
        'religion',
        'blood_type',
        'home_provincial_address',
        'business_address',
        'tin_no',
        'prc_license_no',
        'sss_gsis_no',
        'contact_number',
        'home_telephone_no',
        'company_telephone_no',
        'type_of_industry',
        'type_of_industry_other',
    ];

    protected $casts = [
        'birthday' => 'date',
    ];

    /**
     * Get the user that owns this individual profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Workplace character and demographics (AF-PCN-A1 section 1).
     */
    public function workplaceProfile()
    {
        return $this->hasOne(IndividualWorkplaceProfile::class);
    }
}
