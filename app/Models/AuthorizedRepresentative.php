<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizedRepresentative extends Model
{
    protected $fillable = [
        'organization_profile_id',
        'full_name',
        'position',
        'contact_number',
        'email',
    ];

    /**
     * Get the organization profile this representative belongs to.
     */
    public function organizationProfile()
    {
        return $this->belongsTo(OrganizationProfile::class);
    }
}
