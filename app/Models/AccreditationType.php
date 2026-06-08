<?php

namespace App\Models;

use App\Services\CacheService;
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

    /**
     * Retrieve all accreditation types, from cache when available.
     *
     * @return \Illuminate\Database\Eloquent\Collection<static>
     */
    public static function allCached(): \Illuminate\Database\Eloquent\Collection
    {
        return CacheService::remember(
            CacheService::accreditationTypesKey(),
            CacheService::TTL_REFERENCE,
            fn () => static::all()
        );
    }
}
