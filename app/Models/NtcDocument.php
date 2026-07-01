<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NtcDocument extends Model
{
    protected $fillable = [
        'ntc_report_id',
        'ntc_document_type_id',
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

    public function ntcReport()
    {
        return $this->belongsTo(NtcReport::class);
    }

    public function documentType()
    {
        return $this->belongsTo(NtcDocumentType::class, 'ntc_document_type_id');
    }

    public function evaluatedByUser()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
