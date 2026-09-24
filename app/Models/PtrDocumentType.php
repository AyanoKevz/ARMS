<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PtrDocumentType extends Model
{
    protected $fillable = ['name', 'code', 'entry_type', 'accepted_extensions', 'sort_order'];

    /**
     * Is this the Directory of Participants — encoded row by row in the portal?
     *
     * Deliberately narrower than "not a file upload". The instructor roster is
     * also captured in the portal but is its own entry type, so that anything
     * keyed off the Directory (PostTrainingReport::directoryDocument(), the
     * participant grid, the per-row verdicts) keeps matching one section only.
     */
    public function isEncoded(): bool
    {
        return $this->entry_type === 'encoded';
    }

    /**
     * Is this the list of instructors who conducted the training?
     *
     * Carried over from the parent NTC's declared instructors and amendable,
     * rather than uploaded as a scanned PDF.
     */
    public function isRoster(): bool
    {
        return $this->entry_type === 'roster';
    }

    /**
     * Is this the link to the training video?
     *
     * A URL rather than an upload: a full session runs to gigabytes, so the
     * FATPro hosts it and gives us the address.
     */
    public function isLink(): bool
    {
        return $this->entry_type === 'link';
    }

    /**
     * Is this an uploaded attachment? Checked positively: with four entry
     * types in play, "not encoded" would sweep in the roster and the link.
     */
    public function isFile(): bool
    {
        return $this->entry_type === 'file';
    }

    /**
     * Which kind of wizard step this type renders as, and the value
     * post-training.js switches on in data-kind.
     */
    public function stepKind(): string
    {
        if ($this->isEncoded()) {
            return 'directory';
        }

        if ($this->isRoster()) {
            return 'roster';
        }

        return $this->isLink() ? 'link' : 'file';
    }

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
