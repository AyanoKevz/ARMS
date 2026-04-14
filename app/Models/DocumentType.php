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
    ];

    /**
     * Get the application documents of this type.
     */
    public function applicationDocuments()
    {
        return $this->hasMany(ApplicationDocument::class);
    }
}
