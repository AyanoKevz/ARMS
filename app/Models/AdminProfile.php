<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'position',
        'division_id',
    ];

    /**
     * Get the user that owns this admin profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the division for this admin profile.
     */
    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}
