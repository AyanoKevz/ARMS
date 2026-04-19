<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accreditation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'application_id',
        'accreditation_type_id',
        'accreditation_number',
        'date_of_accreditation',
        'validity_date',
        'status',
    ];

    protected $casts = [
        'date_of_accreditation' => 'date',
        'validity_date'         => 'date',
    ];

    /**
     * Get the user that owns this accreditation.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the application associated with this accreditation.
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the accreditation type for this record.
     */
    public function accreditationType()
    {
        return $this->belongsTo(AccreditationType::class);
    }
}
