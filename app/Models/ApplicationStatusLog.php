<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'status_id',
        'updated_by',
        'remarks',
        'required_updates',
    ];

    protected $casts = [
        'required_updates' => 'array',
    ];

    /**
     * Get the application this log entry belongs to.
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the status for this log entry.
     */
    public function status()
    {
        return $this->belongsTo(ApplicationStatus::class, 'status_id');
    }

    /**
     * Get the admin user who updated the status.
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
