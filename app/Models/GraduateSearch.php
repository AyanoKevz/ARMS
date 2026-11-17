<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One search a verified visitor ran on the Graduate Lookup. Append-only.
 */
class GraduateSearch extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'graduate_visitor_id',
        'search_term',
        'results_count',
        'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function visitor()
    {
        return $this->belongsTo(GraduateVisitor::class, 'graduate_visitor_id');
    }
}
