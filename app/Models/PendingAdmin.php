<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingAdmin extends Model
{
    protected $fillable = [
        'token',
        'email',
        'first_name',
        'last_name',
        'position',
        'division_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Check if the pending registration has expired.
     */
    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }
}
