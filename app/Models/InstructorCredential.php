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
        'reminder_3mo_sent_at',
        'reminder_2mo_sent_at',
        'reminder_1mo_sent_at',
    ];

    protected $casts = [
        'issued_date'          => 'date',
        'validity_date'        => 'date',
        'reminder_3mo_sent_at' => 'datetime',
        'reminder_2mo_sent_at' => 'datetime',
        'reminder_1mo_sent_at' => 'datetime',
    ];

    /**
     * The instructor this credential belongs to.
     */
    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }
}
