<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NtcDocumentType extends Model
{
    protected $fillable = ['name', 'code'];

    public function ntcDocuments()
    {
        return $this->hasMany(NtcDocument::class);
    }
}
