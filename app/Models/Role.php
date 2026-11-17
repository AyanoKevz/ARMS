<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Role names keyed by id. The table holds a handful of fixed rows, so it
     * is read once per request instead of once per user shown on a page.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return once(fn () => static::pluck('name', 'id')->all());
    }

    /**
     * Get the users that belong to this role.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
