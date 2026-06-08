<?php

namespace App\Models;

use App\Services\CacheService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the status logs that use this status.
     */
    public function statusLogs()
    {
        return $this->hasMany(ApplicationStatusLog::class, 'status_id');
    }

    /**
     * Retrieve all statuses, from cache when available.
     *
     * @return \Illuminate\Database\Eloquent\Collection<static>
     */
    public static function allCached(): \Illuminate\Database\Eloquent\Collection
    {
        return CacheService::remember(
            CacheService::applicationStatusesKey(),
            CacheService::TTL_REFERENCE,
            fn () => static::all()
        );
    }

    /**
     * Find a status by name, using the cached collection to avoid a DB hit.
     */
    public static function findByName(string $name): ?static
    {
        return static::allCached()->firstWhere('name', $name);
    }
}
