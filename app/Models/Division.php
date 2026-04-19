<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $fillable = ['name'];

    // Relationship
    public function adminProfiles()
    {
        return $this->hasMany(AdminProfile::class);
    }
}
