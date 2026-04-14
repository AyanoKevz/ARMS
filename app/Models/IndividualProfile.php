<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndividualProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'sex',
        'birthday',
        'region',
        'city',
        'address',
        'photo_path',
    ];

    protected $casts = [
        'birthday' => 'date',
    ];

    /**
     * Get the user that owns this individual profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
