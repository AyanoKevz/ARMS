<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'interview_date',
        'interview_time',
        'mode',
        'venue',
    ];

    protected $casts = [
        'interview_date' => 'date',
    ];

    /**
     * Get the application this interview belongs to.
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
