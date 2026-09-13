<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'accreditation_type_id',
    ];

    /**
     * Get the document fields that belong to this section.
     */
    public function documentFields()
    {
        return $this->hasMany(DocumentField::class);
    }

    /**
     * The accreditation type this checklist belongs to.
     *
     * NULL means the group applies to every type — no seeded group uses that
     * today, but the column stays nullable so a shared requirement can be added
     * without a migration.
     */
    public function accreditationType()
    {
        return $this->belongsTo(AccreditationType::class);
    }

    /**
     * Limit a query to the checklist for one accreditation type, plus any
     * group flagged as global.
     */
    public function scopeForAccreditationType($query, $accreditationTypeId)
    {
        return $query->where(function ($q) use ($accreditationTypeId) {
            $q->where('accreditation_type_id', $accreditationTypeId)
              ->orWhereNull('accreditation_type_id');
        });
    }
}
