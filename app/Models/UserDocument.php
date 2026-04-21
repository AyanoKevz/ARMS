<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'document_field_id',
        'file_path',
        'value',
    ];

    /**
     * Get the user that owns the document.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the document field this document satisfies.
     */
    public function documentField()
    {
        return $this->belongsTo(DocumentField::class);
    }

    /**
     * Get the application documents using this user document.
     */
    public function applicationDocuments()
    {
        return $this->hasMany(ApplicationDocument::class);
    }
}
