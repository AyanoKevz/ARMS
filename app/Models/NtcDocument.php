<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'previous_file_path',
        'previous_original_filename',
        'previous_mime_type',
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

    /** Whether a pending Report of Changes left the acknowledged file behind. */
    public function hasPreviousFile(): bool
    {
        return filled($this->previous_file_path);
    }

    /**
     * Drop the file a Report of Changes replaced, once it has been acknowledged.
     *
     * The new file is the acknowledged one from then on, so the old one is
     * deleted from storage and its columns cleared.
     */
    public function discardPreviousFile(): void
    {
        if (!$this->hasPreviousFile()) {
            return;
        }

        if (Storage::disk('local')->exists($this->previous_file_path)) {
            Storage::disk('local')->delete($this->previous_file_path);
        }

        $this->update([
            'previous_file_path'         => null,
            'previous_original_filename' => null,
            'previous_mime_type'         => null,
        ]);
    }
}
