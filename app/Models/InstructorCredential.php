<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstructorCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'type',
        'number',
        'issued_date',
        'validity_date',
        'training_dates',
        'pdf_path',
        'status',
        'remarks',
    ];

    protected $casts = [
        'issued_date'   => 'date',
        'validity_date' => 'date',
    ];

    /**
     * The instructor this credential belongs to.
     */
    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }
}
