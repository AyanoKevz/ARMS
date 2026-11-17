<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Someone who asked to use the public Graduate Lookup.
 *
 * The lookup needs no account, but it is not anonymous: the visitor gives a
 * name, organization, e-mail and purpose, and only reaches the search page by
 * opening the link e-mailed to them. HCD admins review these rows to see who
 * has been looking up graduates.
 */
class GraduateVisitor extends Model
{
    /** Hours the e-mailed access link stays valid. */
    public const LINK_VALID_HOURS = 24;

    /** The admin roles that can open the visitor log. */
    public const VIEWABLE_ROLES = ['Team Lead', 'Evaluator', 'Training Evaluator'];

    protected $fillable = [
        'name',
        'organization',
        'email',
        'purpose',
        'verified_at',
        'last_accessed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'verified_at'      => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    public function searches()
    {
        return $this->hasMany(GraduateSearch::class)->latest('created_at')->latest('id');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
