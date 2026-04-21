<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'document_field_id',
        'user_document_id',
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
     * Get the specific document field for this application document.
     */
    public function documentField()
    {
        return $this->belongsTo(DocumentField::class);
    }

    /**
     * Get the actual user document value/file path used.
     */
    public function userDocument()
    {
        return $this->belongsTo(UserDocument::class);
    }
}
