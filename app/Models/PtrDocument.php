<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PtrDocument extends Model
{
    protected $fillable = [
        'post_training_report_id',
        'ptr_document_type_id',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'uploaded_at',
        'status',
        'remarks',
        'evaluated_by',
        'evaluated_at',
    ];

    protected $casts = [
        'uploaded_at'  => 'datetime',
        'evaluated_at' => 'datetime',
        'file_size'    => 'integer',
    ];

    public function postTrainingReport()
    {
        return $this->belongsTo(PostTrainingReport::class);
    }

    public function documentType()
    {
        return $this->belongsTo(PtrDocumentType::class, 'ptr_document_type_id');
    }

    public function evaluatedByUser()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
