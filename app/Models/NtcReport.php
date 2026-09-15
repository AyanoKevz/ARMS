<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class NtcReport extends Model
{
    /**
     * Working days a FATPro gets to file the post training report after the
     * training ends, keyed by training type code (OSHC MC 04 series 2025).
     */
    public const POST_TRAINING_DAYS = [
        'EFA' => 1,
        'OFA' => 2,
        'SFA' => 4,
    ];

    /** Fallback when a training type code is not in the table above. */
    public const POST_TRAINING_DAYS_DEFAULT = 4;

    protected $fillable = [
        'accreditation_id',
        'ntc_training_type_id',
        'ntc_training_mode_id',
        'venue',
        'training_start_date',
        'training_end_date',
        'status',
        'submitted_at',
        'acknowledged_at',
        'acknowledged_by',
        'remarks',
        'ptr_reminder_sent_at',
        'ptr_overdue_notified_at',
        'ptr_last_reminded_on',
    ];

    protected $casts = [
        'training_start_date'     => 'date',
        'training_end_date'       => 'date',
        'submitted_at'            => 'datetime',
        'acknowledged_at'         => 'datetime',
        'ptr_reminder_sent_at'    => 'datetime',
        'ptr_overdue_notified_at' => 'datetime',
        'ptr_last_reminded_on'    => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function accreditation()
    {
        return $this->belongsTo(Accreditation::class);
    }

    public function trainingType()
    {
        return $this->belongsTo(NtcTrainingType::class, 'ntc_training_type_id');
    }

    public function trainingMode()
    {
        return $this->belongsTo(NtcTrainingMode::class, 'ntc_training_mode_id');
    }

    public function documents()
    {
        return $this->hasMany(NtcDocument::class);
    }

    public function acknowledgedByUser()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function postTrainingReport()
    {
        return $this->hasOne(PostTrainingReport::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate a reference number for the NTC report.
     */
    public function getReferenceNumberAttribute(): string
    {
        return 'NTC-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Check if 10-working-day rule is satisfied for the training start date.
     */
    public static function isValidStartDate(Carbon $startDate): bool
    {
        $workingDaysRequired = 10;
        $today = Carbon::today();
        $workingDaysCounted = 0;
        $cursor = $today->copy()->addDay();

        while ($workingDaysCounted < $workingDaysRequired) {
            // Skip weekends (Saturday = 6, Sunday = 0)
            if (!$cursor->isWeekend()) {
                $workingDaysCounted++;
            }
            if ($workingDaysCounted < $workingDaysRequired) {
                $cursor->addDay();
            }
        }

        // The earliest allowed training start date is $cursor
        return $startDate->greaterThanOrEqualTo($cursor);
    }

    /**
     * Get the earliest allowed training start date (10 working days from today).
     */
    public static function earliestAllowedStartDate(): Carbon
    {
        $workingDaysRequired = 10;
        $today = Carbon::today();
        $workingDaysCounted = 0;
        $cursor = $today->copy()->addDay();

        while ($workingDaysCounted < $workingDaysRequired) {
            if (!$cursor->isWeekend()) {
                $workingDaysCounted++;
            }
            if ($workingDaysCounted < $workingDaysRequired) {
                $cursor->addDay();
            }
        }

        return $cursor;
    }

    /**
     * Get the deadline date for submitting a Report of Changes.
     */
    public function reportChangesDeadlineDate(): ?Carbon
    {
        if (!$this->training_start_date) {
            return null;
        }

        $startDate = $this->training_start_date->copy()->startOfDay();

        // Count working days backwards from training_start_date
        $cursor = $startDate->copy()->subDay();
        $workingDaysCounted = 0;

        while ($workingDaysCounted < 3) {
            if (!$cursor->isWeekend()) {
                $workingDaysCounted++;
            }
            if ($workingDaysCounted < 3) {
                $cursor->subDay();
            }
        }

        return $cursor;
    }

    /**
     * Check if the report of changes can be submitted (at least 3 working days before training_start_date).
     */
    public function canSubmitReportChanges(): bool
    {
        $deadline = $this->reportChangesDeadlineDate();
        if (!$deadline) {
            return false;
        }
        return Carbon::today()->lessThanOrEqualTo($deadline);
    }

    // ── Post Training Report ──────────────────────────────────────────────────

    /**
     * Working days allowed to file the post training report, by training type.
     */
    public function postTrainingDaysAllowed(): int
    {
        $code = strtoupper($this->trainingType->code ?? '');

        return self::POST_TRAINING_DAYS[$code] ?? self::POST_TRAINING_DAYS_DEFAULT;
    }

    /**
     * The last day the post training report may be submitted: N working days
     * after the training ends, where N depends on the training type.
     */
    public function postTrainingDeadlineDate(): ?Carbon
    {
        if (!$this->training_end_date) {
            return null;
        }

        return self::addWorkingDays(
            $this->training_end_date->copy()->startOfDay(),
            $this->postTrainingDaysAllowed()
        );
    }

    /**
     * Has the last training day arrived? The report opens on that day rather
     * than the morning after, so a FATPro finishing a one-day course can file
     * it the same day instead of waiting for the clock to roll over. The
     * deadline is counted from the end date either way, so this only widens
     * the window, never shortens it.
     */
    public function hasTrainingConcluded(): bool
    {
        return $this->training_end_date
            && Carbon::today()->greaterThanOrEqualTo($this->training_end_date->copy()->startOfDay());
    }

    /**
     * Does this NTC still owe a post training report? True once the training has
     * concluded and no report has been accepted for it yet.
     */
    public function requiresPostTrainingReport(): bool
    {
        if ($this->status !== 'acknowledged' || !$this->hasTrainingConcluded()) {
            return false;
        }

        $report = $this->postTrainingReport;

        return !$report || !$report->isAccepted();
    }

    /**
     * Has the FATPro missed the deadline without an accepted report on file?
     */
    public function isPostTrainingReportOverdue(): bool
    {
        $deadline = $this->postTrainingDeadlineDate();

        return $this->requiresPostTrainingReport()
            && $deadline
            && Carbon::today()->greaterThan($deadline);
    }

    /**
     * Calendar days left until the post training deadline. Negative once past
     * it. Drives the countdown pill on the portal, where a plain "3 days left"
     * is what a reader expects to see against a calendar date.
     */
    public function postTrainingDaysRemaining(): ?int
    {
        $deadline = $this->postTrainingDeadlineDate();
        if (!$deadline) {
            return null;
        }

        return Carbon::today()->diffInDays($deadline, false);
    }

    /**
     * WORKING days left until the deadline, counting today when today is itself
     * a working day. The allowance is expressed in working days, so a reminder
     * that says "2 working days left" has to count the same way — over a
     * weekend the calendar figure and this one diverge sharply.
     *
     * Returns 0 on the deadline day itself, and null once it has passed.
     */
    public function postTrainingWorkingDaysRemaining(): ?int
    {
        $deadline = $this->postTrainingDeadlineDate();
        if (!$deadline) {
            return null;
        }

        $today = Carbon::today();
        if ($today->greaterThan($deadline)) {
            return null;
        }

        $count  = 0;
        $cursor = $today->copy();

        while ($cursor->lessThan($deadline)) {
            $cursor->addDay();
            if (!$cursor->isWeekend()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Advance a date by N working days, skipping weekends.
     */
    public static function addWorkingDays(Carbon $from, int $workingDays): Carbon
    {
        $cursor = $from->copy();
        $counted = 0;

        while ($counted < $workingDays) {
            $cursor->addDay();
            if (!$cursor->isWeekend()) {
                $counted++;
            }
        }

        return $cursor;
    }
}

