<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccreditationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the applications under this accreditation type.
     */
    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}
