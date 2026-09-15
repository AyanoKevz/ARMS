<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostTrainingReport extends Model
{
    protected $fillable = [
        'ntc_report_id',
        'accreditation_id',
        'status',
        'due_date',
        'submitted_at',
        'accepted_at',
        'accepted_by',
        'remarks',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'submitted_at' => 'datetime',
        'accepted_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function ntcReport()
    {
        return $this->belongsTo(NtcReport::class);
    }

    public function accreditation()
    {
        return $this->belongsTo(Accreditation::class);
    }

    public function documents()
    {
        return $this->hasMany(PtrDocument::class);
    }

    public function acceptedByUser()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Reference number for the post training report.
     */
    public function getReferenceNumberAttribute(): string
    {
        return 'PTR-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Documents the FATPro still has to re-upload: declined and the file wiped.
     */
    public function declinedDocuments()
    {
        return $this->documents->filter(fn ($d) => $d->status === 'rejected' && !$d->file_path);
    }

    /**
     * Was this report submitted after its deadline?
     */
    public function wasSubmittedLate(): bool
    {
        if (!$this->due_date || !$this->submitted_at) {
            return false;
        }

        return $this->submitted_at->copy()->startOfDay()->greaterThan($this->due_date);
    }
}
