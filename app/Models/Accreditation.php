<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accreditation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'application_id',
        'accreditation_type_id',
        'accreditation_number',
        'date_of_accreditation',
        'validity_date',
        'status',
        'scanned_certificate',
        'reminder_3mo_sent_at',
        'reminder_2mo_sent_at',
        'reminder_1mo_sent_at',
        'fa_cert_reminder_3mo_sent_at',
        'fa_cert_reminder_2mo_sent_at',
        'fa_cert_reminder_1mo_sent_at',
        'fa_cert_expired_notified_at',
    ];

    protected $casts = [
        'date_of_accreditation' => 'date',
        'validity_date'         => 'date',
        'reminder_3mo_sent_at'  => 'datetime',
        'reminder_2mo_sent_at'  => 'datetime',
        'reminder_1mo_sent_at'  => 'datetime',
        'fa_cert_reminder_3mo_sent_at' => 'datetime',
        'fa_cert_reminder_2mo_sent_at' => 'datetime',
        'fa_cert_reminder_1mo_sent_at' => 'datetime',
        'fa_cert_expired_notified_at'  => 'datetime',
    ];

    /**
     * Get the user that owns this accreditation.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the application associated with this accreditation.
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the accreditation type for this record.
     */
    public function accreditationType()
    {
        return $this->belongsTo(AccreditationType::class);
    }

    // ── First-aider certificate ───────────────────────────────────────────────
    // Filed with the application as two documentary fields: the certificate
    // itself (PREM_07) and its validity date (PREM_DATE). FATPro applications
    // only — another accreditation type simply has neither.

    public const FA_CERT_FIELD      = 'PREM_07';
    public const FA_CERT_DATE_FIELD = 'PREM_DATE';

    /** Months before expiry at which "expiring soon" starts (and reminders begin). */
    public const FA_CERT_WARNING_MONTHS = 3;

    public function firstAiderCertificateSubmissions()
    {
        return $this->hasMany(FirstAiderCertificateSubmission::class)->latest('id');
    }

    /** The renewal waiting on the evaluator, if any. */
    public function pendingFirstAiderCertificate()
    {
        return $this->hasOne(FirstAiderCertificateSubmission::class)
            ->where('status', 'pending')
            ->latestOfMany();
    }

    /** The most recent renewal, whatever became of it. */
    public function latestFirstAiderCertificate()
    {
        return $this->hasOne(FirstAiderCertificateSubmission::class)->latestOfMany();
    }

    /**
     * The certificate and validity-date documents of this accreditation's
     * application, keyed 'file' and 'date' (either may be null).
     *
     * @return array{file: ?ApplicationDocument, date: ?ApplicationDocument}
     */
    public function firstAiderDocuments(): array
    {
        // One lookup per request: the card, the state and the validity date
        // all read these.
        if ($this->faDocsCache !== null) {
            return $this->faDocsCache;
        }

        $docs = ApplicationDocument::query()
            ->where('application_id', $this->application_id)
            ->whereHas('documentField', fn ($q) => $q->whereIn('code', [self::FA_CERT_FIELD, self::FA_CERT_DATE_FIELD]))
            ->with(['documentField', 'userDocument'])
            ->get()
            ->keyBy(fn ($doc) => $doc->documentField->code);

        return $this->faDocsCache = [
            'file' => $docs->get(self::FA_CERT_FIELD),
            'date' => $docs->get(self::FA_CERT_DATE_FIELD),
        ];
    }

    /** @var array{file: ?ApplicationDocument, date: ?ApplicationDocument}|null */
    private ?array $faDocsCache = null;

    /** Whether this accreditation carries a first-aider certificate at all. */
    public function hasFirstAiderCertificate(): bool
    {
        return $this->firstAiderDocuments()['date'] !== null;
    }

    /** The certificate's current validity date, or null if none is on file. */
    public function firstAiderCertificateValidity(): ?\Carbon\Carbon
    {
        $value = $this->firstAiderDocuments()['date']?->userDocument?->value;

        if (!$value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 'valid', 'expiring' (within FA_CERT_WARNING_MONTHS), 'expired', or
     * 'none' when there is no date on file. Valid through its last day.
     */
    public function firstAiderCertificateState(?\Carbon\Carbon $validity = null): string
    {
        $validity ??= $this->firstAiderCertificateValidity();

        if (!$validity) {
            return 'none';
        }

        $today = \Carbon\Carbon::today();

        if ($validity->lessThan($today)) {
            return 'expired';
        }

        return $validity->lessThanOrEqualTo($today->copy()->addMonths(self::FA_CERT_WARNING_MONTHS))
            ? 'expiring'
            : 'valid';
    }

    /**
     * Make an approved renewal the certificate on file: the new PDF and date
     * replace the old ones, both documents are marked approved, and the
     * reminder clock starts over for the new date.
     */
    public function applyFirstAiderCertificate(FirstAiderCertificateSubmission $submission): void
    {
        $docs = $this->firstAiderDocuments();

        if ($fileDoc = $docs['file']) {
            $old = $fileDoc->userDocument?->file_path;

            $fileDoc->userDocument?->update(['file_path' => $submission->file_path]);
            $fileDoc->update(['status' => 'approved', 'remarks' => null]);

            if ($old && $old !== $submission->file_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($old)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($old);
            }
        }

        if ($dateDoc = $docs['date']) {
            $dateDoc->userDocument?->update(['value' => $submission->validity_date->toDateString()]);
            $dateDoc->update(['status' => 'approved', 'remarks' => null]);
        }

        $this->faDocsCache = null;

        $this->update([
            'fa_cert_reminder_3mo_sent_at' => null,
            'fa_cert_reminder_2mo_sent_at' => null,
            'fa_cert_reminder_1mo_sent_at' => null,
            'fa_cert_expired_notified_at'  => null,
        ]);
    }
}
