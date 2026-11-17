<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base for the ten repeatable practitioner CV sections.
 *
 * Every section table shares the same owner columns — user_id, application_id
 * and sort_order (see 2026_09_13_000001_create_practitioner_tables) — so the
 * ownership relations live here once.
 */
abstract class PractitionerRecord extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
