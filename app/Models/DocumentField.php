<?php

namespace App\Models;

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
}
