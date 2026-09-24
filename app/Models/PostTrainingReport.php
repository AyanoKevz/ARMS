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
        'training_video_url',
        'applicant_remarks',
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

    public function participants()
    {
        return $this->hasMany(PtrParticipant::class)->orderBy('row_no')->orderBy('id');
    }

    /**
     * The instructors recorded as having actually conducted the training.
     *
     * Seeded from the parent NTC's declared instructors when the report is
     * created, then amendable on its own. Kept separate from
     * NtcReport::instructors() so correcting who taught never rewrites who was
     * declared.
     */
    public function instructors()
    {
        return $this->belongsToMany(
            InstructorPerson::class,
            'ptr_instructors',
            'post_training_report_id',
            'instructor_person_id'
        )
            ->withTimestamps()
            ->orderBy('last_name')
            ->orderBy('first_name');
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
        return $this->documents->filter(
            fn ($d) => $d->status === 'rejected' && !$d->file_path && $d->documentType?->isFile()
        );
    }

    /**
     * Was this report submitted after its deadline?
     */
    /**
     * The ptr_documents row standing in for the Directory of Participants.
     * It carries no file — only the section-wide status and remarks.
     */
    public function directoryDocument()
    {
        return $this->documents->first(fn ($d) => $d->documentType?->isEncoded());
    }

    /**
     * The ptr_documents row standing in for the instructor roster. Like the
     * Directory it carries no file, only the section's status and remarks.
     */
    public function instructorsDocument()
    {
        return $this->documents->first(fn ($d) => $d->documentType?->isRoster());
    }

    /**
     * The ptr_documents row standing in for the training video link. Carries
     * no file either — the link itself lives on the report.
     */
    public function videoDocument()
    {
        return $this->documents->first(fn ($d) => $d->documentType?->isLink());
    }

    /**
     * Does the FATPro owe a new video link?
     */
    public function hasVideoCorrections(): bool
    {
        return $this->videoDocument()?->status === 'rejected';
    }

    /**
     * Participants the evaluator turned down. The Directory is corrected by
     * editing these rows, not by uploading a replacement file.
     */
    public function rejectedParticipants()
    {
        return $this->participants->filter(fn ($p) => $p->isRejected());
    }

    /**
     * Does the FATPro owe a correction on the Directory?
     */
    public function hasDirectoryCorrections(): bool
    {
        return $this->rejectedParticipants()->isNotEmpty();
    }

    /**
     * Does the FATPro owe a correction on the instructor roster?
     *
     * Like the Directory, it is put right by editing the selection rather than
     * re-uploading anything, so it never reaches declinedDocuments().
     */
    public function hasInstructorCorrections(): bool
    {
        return $this->instructorsDocument()?->status === 'rejected';
    }

    /**
     * Does anything on this report need putting right?
     *
     * A report is sent back in three different ways and the FATPro should not
     * have to notice which: a declined attachment is re-uploaded, declined
     * participants are edited row by row, and a declined instructor list is
     * re-chosen. Any combination can be outstanding at once.
     */
    public function needsCorrections(): bool
    {
        return $this->declinedDocuments()->isNotEmpty()
            || $this->hasDirectoryCorrections()
            || $this->hasInstructorCorrections()
            || $this->hasVideoCorrections();
    }

    /**
     * How many separate things are waiting to be corrected.
     *
     * Counts sections, not rows: three declined participants are one thing to
     * go and fix, which is what the button on the portal is offering to open.
     */
    public function declinedSectionCount(): int
    {
        return $this->declinedDocuments()->count()
            + ($this->hasDirectoryCorrections() ? 1 : 0)
            + ($this->hasInstructorCorrections() ? 1 : 0)
            + ($this->hasVideoCorrections() ? 1 : 0);
    }

    public function wasSubmittedLate(): bool
    {
        if (!$this->due_date || !$this->submitted_at) {
            return false;
        }

        return $this->submitted_at->copy()->startOfDay()->greaterThan($this->due_date);
    }
}
