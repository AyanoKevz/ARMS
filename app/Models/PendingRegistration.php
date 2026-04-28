<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    protected $fillable = [
        'token',
        'email',
        'password',
        'profile_type',
        'accreditation_type_id',
        'form_data',
        'documents_data',
        'instructors_data',
        'expires_at',
    ];

    protected $casts = [
        'form_data'       => 'array',
        'documents_data'  => 'array',
        'instructors_data'=> 'array',
        'expires_at'      => 'datetime',
    ];

    /**
     * Check if this pending registration token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
