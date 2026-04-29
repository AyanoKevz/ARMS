<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instructor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'service_agreement_path',
        'status',
        'remarks',
    ];

    /**
     * The FATPro applicant this instructor belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All credentials attached to this instructor.
     */
    public function credentials()
    {
        return $this->hasMany(InstructorCredential::class);
    }

    /**
     * Convenience: get credential by type string.
     */
    public function credential(string $type): ?InstructorCredential
    {
        return $this->credentials->firstWhere('type', $type);
    }
}
