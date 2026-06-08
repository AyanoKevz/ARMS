<?php

namespace App\Models;

use App\Services\CacheService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentField extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type_id',
        'name',
        'code',
        'input_type',
    ];

    /**
     * Get the document type (section) this field belongs to.
     */
    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * Get all user documents submitted for this field.
     */
    public function userDocuments()
    {
        return $this->hasMany(UserDocument::class);
    }

    /**
     * Retrieve all document fields, from cache when available.
     * Keyed by 'code' for O(1) lookup.
     *
     * @return \Illuminate\Support\Collection<string, static>  (keyed by code)
     */
    public static function allCached(): \Illuminate\Support\Collection
    {
        return CacheService::remember(
            CacheService::documentFieldsKey(),
            CacheService::TTL_REFERENCE,
            fn () => static::all()->keyBy('code')
        );
    }
}
