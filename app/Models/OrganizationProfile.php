<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'address',
        'head_name',
        'designation',
        'telephone',
        'fax',
        'email',
        'logo_path',
    ];

    protected $casts = [
    ];

    /**
     * Get the user that owns this organization profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the authorized representatives for this organization.
     */
    public function authorizedRepresentatives()
    {
        return $this->hasMany(\App\Models\AuthorizedRepresentative::class, 'organization_profile_id');
    }
}
