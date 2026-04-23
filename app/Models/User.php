<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\CustomResetPassword;

class User extends Authenticatable
{

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'role_id',
        'profile_type',
        'email_verified_at',
        'user_photo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password'          => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────
    //  Relationships
    // ─────────────────────────────────────────────

    /**
     * Get the role of this user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the admin profile (if this user is an admin).
     */
    public function adminProfile()
    {
        return $this->hasOne(AdminProfile::class);
    }

    /**
     * Get the individual profile (if this user is an individual applicant).
     */
    public function individualProfile()
    {
        return $this->hasOne(IndividualProfile::class);
    }

    /**
     * Get the organization profile (if this user is an organization applicant).
     */
    public function organizationProfile()
    {
        return $this->hasOne(OrganizationProfile::class);
    }

    /**
     * Get the applications submitted by this user.
     */
    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Get the applications handled by this admin user.
     */
    public function handledApplications()
    {
        return $this->hasMany(Application::class, 'handled_by_admin_id');
    }

    /**
     * Get the accreditations for this user.
     */
    public function accreditations()
    {
        return $this->hasMany(Accreditation::class);
    }

    /**
     * Get the documents uploaded by this user.
     */
    public function userDocuments()
    {
        return $this->hasMany(UserDocument::class);
    }

    /**
     * Get the user's display name based on their profile.
     */
    public function getNameAttribute()
    {
        if ($this->role && strtolower($this->role->name) === 'admin') {
            $admin = $this->adminProfile;
            return $admin ? "{$admin->first_name} {$admin->last_name}" : 'Admin';
        }

        if ($this->profile_type === 'Organization') {
            return $this->organizationProfile->name ?? 'Organization User';
        }

        $ind = $this->individualProfile;
        if ($ind) {
            return "{$ind->first_name} {$ind->last_name}";
        }

        return 'User';
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }
}

