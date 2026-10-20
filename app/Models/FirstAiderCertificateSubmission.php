<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A FATPro's renewed first-aider certificate, submitted during an accreditation
 * and waiting on (or decided by) the application's assigned evaluator.
 *
 * Only an approved submission replaces the certificate on file — see
 * Accreditation::applyFirstAiderCertificate().
 */
class FirstAiderCertificateSubmission extends Model
{
    protected $fillable = [
        'accreditation_id',
        'user_id',
        'file_path',
        'original_filename',
        'validity_date',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'validity_date' => 'date',
        'reviewed_at'   => 'datetime',
    ];

    public function accreditation()
    {
        return $this->belongsTo(Accreditation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedByUser()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
