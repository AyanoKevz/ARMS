<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the status logs that use this status.
     */
    public function statusLogs()
    {
        return $this->hasMany(ApplicationStatusLog::class, 'status_id');
    }
}
