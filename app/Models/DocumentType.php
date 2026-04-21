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
     * Get the document fields that belong to this section.
     */
    public function documentFields()
    {
        return $this->hasMany(DocumentField::class);
    }
}
