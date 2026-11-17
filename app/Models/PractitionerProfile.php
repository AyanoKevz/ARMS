<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One practitioner application's scalars: years of OSH experience, the five
 * yes/no declarations with their "give details" answers, the ID photo and
 * the generated AF-PCN-A1 form.
 */
class PractitionerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'application_id',
        'total_osh_experience_years',
        'photo_path',
        'application_form_path',
        'has_pending_admin_case',
        'has_pending_criminal_case',
        'pending_case_details',
        'has_crime_conviction',
        'crime_conviction_details',
        'has_admin_offense_conviction',
        'admin_offense_details',
        'has_forced_separation',
        'forced_separation_reasons',
    ];

    protected $casts = [
        'total_osh_experience_years'   => 'integer',
        'has_pending_admin_case'       => 'boolean',
        'has_pending_criminal_case'    => 'boolean',
        'has_crime_conviction'         => 'boolean',
        'has_admin_offense_conviction' => 'boolean',
        'has_forced_separation'        => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
