<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminRole extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * Get the profiles associated with this admin role.
     */
    public function adminProfiles()
    {
        return $this->hasMany(AdminProfile::class);
    }

    /**
     * Get the pending admins associated with this admin role.
     */
    public function pendingAdmins()
    {
        return $this->hasMany(PendingAdmin::class);
    }
}
