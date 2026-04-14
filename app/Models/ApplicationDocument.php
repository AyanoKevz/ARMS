<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'document_type_id',
        'file_path',
        'status',
        'remarks',
    ];

    /**
     * Get the application this document belongs to.
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the document type of this document.
     */
    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }
}
