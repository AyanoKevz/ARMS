<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'accreditation_type_id',
        'application_type',
        'assigned_evaluator_id',
        'tracking_number',
        'submitted_at',
        'compliance_due_date',
        'archived_at',
        'archive_reason',
        'reapply_after',
    ];

    protected $casts = [
        'submitted_at'        => 'datetime',
        'compliance_due_date' => 'date',
        'archived_at'         => 'datetime',
        'reapply_after'       => 'date',
    ];

    /**
     * Get the applicant (user) who submitted this application.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the accreditation type for this application.
     */
    public function accreditationType()
    {
        return $this->belongsTo(AccreditationType::class);
    }

    /**
     * Get the evaluator assigned to this application via decking.
     */
    public function assignedEvaluator()
    {
        return $this->belongsTo(User::class, 'assigned_evaluator_id');
    }

    /**
     * Get the interview associated with this application.
     */
    public function interview()
    {
        return $this->hasOne(Interview::class);
    }

    /** The Accreditation division interview slot this applicant booked. */
    public function interviewSlot()
    {
        return $this->hasOne(InterviewSlot::class);
    }

    /**
     * Get the uploaded documents for this application.
     */
    public function documents()
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * Get the status history logs for this application.
     */
    public function statusLogs()
    {
        return $this->hasMany(ApplicationStatusLog::class);
    }

    /**
     * Get the latest status log (current status).
     */
    public function latestStatus()
    {
        return $this->hasOne(ApplicationStatusLog::class)->latestOfMany();
    }

    /**
     * Get the accreditation associated with this application.
     */
    public function accreditation()
    {
        return $this->hasOne(Accreditation::class);
    }

    /**
     * Get the payment requirements associated with this application.
     */
    public function payment()
    {
        return $this->hasOne(ApplicationPayment::class);
    }

    /**
     * Get all PCT (Process Cycle Time) entries for this application.
     */
    public function pctEntries()
    {
        return $this->hasMany(PctEntry::class)->orderBy('step_number');
    }

    /**
     * Get the currently active PCT entry.
     */
    public function activePctEntry()
    {
        return $this->hasOne(PctEntry::class)->where('is_active', true)->latestOfMany();
    }

    /**
     * Calculate the total PCT elapsed in working days across all steps.
     */
    public function totalPctDays(): float
    {
        $totalSeconds = 0;
        foreach ($this->pctEntries as $entry) {
            $totalSeconds += $entry->totalElapsedSeconds();
        }
        return round($totalSeconds / 32400, 1);
    }

    /**
     * Get the instructors associated with this application.
     */
    public function instructors()
    {
        return $this->hasMany(Instructor::class);
    }

    /* ── Practitioner CV sections (AF-PCN-A1 sections 2–10) ──────────── */

    public function practitionerEducations()
    {
        return $this->hasMany(PractitionerEducation::class)->orderBy('sort_order');
    }

    public function practitionerLicenses()
    {
        return $this->hasMany(PractitionerProfessionalLicense::class)->orderBy('sort_order');
    }

    public function practitionerWorkExperiences()
    {
        return $this->hasMany(PractitionerWorkExperience::class)->orderBy('sort_order');
    }

    public function practitionerTrainings()
    {
        return $this->hasMany(PractitionerTraining::class)->orderBy('sort_order');
    }

    public function practitionerLectures()
    {
        return $this->hasMany(PractitionerLecture::class)->orderBy('sort_order');
    }

    public function practitionerSkills()
    {
        return $this->hasMany(PractitionerSkill::class)->orderBy('sort_order');
    }

    public function practitionerAwards()
    {
        return $this->hasMany(PractitionerAward::class)->orderBy('sort_order');
    }

    public function practitionerExaminations()
    {
        return $this->hasMany(PractitionerExamination::class)->orderBy('sort_order');
    }

    public function practitionerMemberships()
    {
        return $this->hasMany(PractitionerMembership::class)->orderBy('sort_order');
    }

    public function practitionerReferences()
    {
        return $this->hasMany(PractitionerReference::class)->orderBy('sort_order');
    }

    /**
     * Practitioner per-application scalars, declarations and the generated
     * AF-PCN-A1 form.
     */
    public function practitionerProfile()
    {
        return $this->hasOne(PractitionerProfile::class);
    }

    /**
     * Whether this application goes through the OSH Practitioner process —
     * an OSH Practitioner OR an OSH Consultant application. The two share the
     * AF-PCN-A1 form, its evaluation, interview, PCT and certificate; only
     * the documents, fee and photo background differ (isConsultant()).
     */
    public function isPractitioner(): bool
    {
        return \App\Services\PractitionerApplicationService::handles($this->accreditation_type_id);
    }

    /** Whether this is an OSH Consultant application (see isPractitioner()). */
    public function isConsultant(): bool
    {
        return (int) $this->accreditation_type_id === (int) config('accreditation.types.consultant');
    }

    /** The accreditation fee, formatted: "450.00". */
    public function paymentAmount(): string
    {
        $amounts = config('accreditation.payment_amounts', []);

        return number_format((float) ($amounts[(int) $this->accreditation_type_id] ?? $amounts['default'] ?? 900), 2);
    }

    /** What the applicant is accredited as: "OSH Practitioner", "First Aid Training Provider". */
    public function accreditationTitle(): string
    {
        return config('accreditation.accreditation_division_types.' . (int) $this->accreditation_type_id)
            ?? 'First Aid Training Provider';
    }

    /** Whether the Accreditation division (not HCD) handles this application. */
    public function isHandledByAccreditationDivision(): bool
    {
        return \App\Support\AdminAccess::isAccreditationDivisionType($this->accreditation_type_id);
    }

    /**
     * The admin page for this application, in whichever portal processes it.
     * Emails and in-app notifications link here instead of hardcoding HCD.
     */
    public function adminShowUrl(): string
    {
        return $this->isHandledByAccreditationDivision()
            ? route('admin.accreditation.practitioner.show', $this->id)
            : route('admin.hcd.applications.show', $this->id);
    }

    /** The admin list a brand-new submission lands in. */
    public function adminPendingUrl(): string
    {
        return match (true) {
            $this->isConsultant()                     => route('admin.accreditation.consultant.new.pending'),
            $this->isHandledByAccreditationDivision() => route('admin.accreditation.practitioner.new.pending'),
            default                                   => route('admin.hcd.applications.pending'),
        };
    }

    /**
     * Whether this application bypasses the interview stage.
     *
     * A renewing FATPro was already interviewed in the cycle that produced the
     * accreditation they are renewing, so there is nothing to interview them for
     * again: an approved evaluation goes straight to payment, and PCT Steps 4-6
     * (Pending Interview / Interview / Interview Result) are never started.
     *
     * Reinstatement is deliberately NOT included — a lapsed accreditation is
     * re-assessed in full, interview included.
     */
    public function skipsInterview(): bool
    {
        return strtolower((string) $this->application_type) === 'renewal';
    }
}
