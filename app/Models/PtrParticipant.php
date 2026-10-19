<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One encoded row of the Directory of Participants.
 *
 * Evaluators judge the Directory as a whole on its PtrDocument section row,
 * which also holds the remarks. That verdict is copied onto every participant
 * (status lives here), so a declined Directory opens every row for correction.
 */
class PtrParticipant extends Model
{
    /** ID picture ceiling, in kilobytes (5 MB) — deliberately not the 25 MB document ceiling. */
    public const MAX_PHOTO_KB = 5120;

    /** Image formats accepted for an ID picture. */
    public const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /** The encodable columns, in the order the Directory presents them. */
    public const FIELDS = [
        'certificate_number',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'sex',
        'age',
        'company',
        'position',
        'company_city',
        'company_region',
        'industry',
        'total_workers',
        'company_email',
        'personal_email',
        'mobile_no',
        'company_landline',
        'mode_of_training',
        'batch_no',
    ];

    protected $fillable = [
        'post_training_report_id',
        'row_no',
        'certificate_number',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'sex',
        'age',
        'company',
        'position',
        'company_city',
        'company_region',
        'industry',
        'total_workers',
        'company_email',
        'personal_email',
        'mobile_no',
        'company_landline',
        'id_picture_path',
        'id_picture_filename',
        'id_picture_size',
        'mode_of_training',
        'batch_no',
        'pre_test_score',
        'post_test_score',
        'is_catch_up',
        'catch_up_ntc_report_id',
        'catch_up_start_date',
        'catch_up_end_date',
        'status',
        'evaluated_by',
        'evaluated_at',
    ];

    protected $casts = [
        'row_no'          => 'integer',
        'age'             => 'integer',
        'total_workers'   => 'integer',
        'id_picture_size' => 'integer',
        'is_catch_up'         => 'boolean',
        'catch_up_start_date' => 'date',
        'catch_up_end_date'   => 'date',
        'evaluated_at'    => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function postTrainingReport()
    {
        return $this->belongsTo(PostTrainingReport::class);
    }

    /** For a catch-up participant: the earlier training they started in. */
    public function catchUpSource()
    {
        return $this->belongsTo(NtcReport::class, 'catch_up_ntc_report_id');
    }

    public function evaluatedByUser()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function fullName(): string
    {
        $name = trim("{$this->first_name} {$this->middle_name} {$this->last_name}");

        return $this->suffix ? "{$name} {$this->suffix}" : $name;
    }

    /**
     * First, middle (if any) and last name — how the Pre- and Post-Test
     * Summary Evaluation names each participant.
     */
    public function scoreSheetName(): string
    {
        return trim(preg_replace('/\s+/', ' ', "{$this->first_name} {$this->middle_name} {$this->last_name}"));
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function hasIdPicture(): bool
    {
        return (bool) $this->id_picture_path;
    }

    /**
     * Remove the stored ID picture from disk. Used when a row is deleted or the
     * picture is replaced, so photos never stack up in the FATPro's folder.
     */
    public function deleteIdPicture(): void
    {
        if ($this->id_picture_path && Storage::disk('local')->exists($this->id_picture_path)) {
            Storage::disk('local')->delete($this->id_picture_path);
        }
    }
}
