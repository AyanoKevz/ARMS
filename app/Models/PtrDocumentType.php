<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PtrDocumentType extends Model
{
    protected $fillable = ['name', 'code', 'accepted_extensions', 'sort_order'];

    public function ptrDocuments()
    {
        return $this->hasMany(PtrDocument::class);
    }

    /**
     * Accepted extensions as an array, e.g. ['xlsx', 'xls'].
     */
    public function acceptedExtensions(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->accepted_extensions)
        )));
    }

    /**
     * Comma-separated accept attribute for a file input, e.g. ".xlsx,.xls".
     */
    public function acceptAttribute(): string
    {
        return implode(',', array_map(fn ($ext) => '.' . $ext, $this->acceptedExtensions()));
    }

    /**
     * Human-readable format list for the UI, e.g. "XLSX, XLS".
     */
    public function formatLabel(): string
    {
        return implode(', ', array_map('strtoupper', $this->acceptedExtensions()));
    }

    /**
     * The form field name this document type is uploaded under.
     */
    public function inputName(): string
    {
        return 'file_' . strtolower($this->code);
    }
}
