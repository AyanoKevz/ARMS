<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'accreditation_type_id',
        'application_type',
        'handled_by_admin_id',
        'tracking_number',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    /**
     * Get the applicant (user) who submitted this application.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the accreditation type for this application.
     */
    public function accreditationType()
    {
        return $this->belongsTo(AccreditationType::class);
    }

    /**
     * Get the admin who is handling this application.
     */
    public function handledByAdmin()
    {
        return $this->belongsTo(User::class, 'handled_by_admin_id');
    }

    /**
     * Get the interview associated with this application.
     */
    public function interview()
    {
        return $this->hasOne(Interview::class);
    }

    /**
     * Get the uploaded documents for this application.
     */
    public function documents()
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * Get the status history logs for this application.
     */
    public function statusLogs()
    {
        return $this->hasMany(ApplicationStatusLog::class);
    }

    /**
     * Get the latest status log (current status).
     */
    public function latestStatus()
    {
        return $this->hasOne(ApplicationStatusLog::class)->latestOfMany();
    }
}
