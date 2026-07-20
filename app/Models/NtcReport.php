<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class NtcReport extends Model
{
    protected $fillable = [
        'accreditation_id',
        'ntc_training_type_id',
        'ntc_training_mode_id',
        'training_start_date',
        'training_end_date',
        'status',
        'submitted_at',
        'acknowledged_at',
        'acknowledged_by',
        'remarks',
    ];

    protected $casts = [
        'training_start_date' => 'date',
        'training_end_date'   => 'date',
        'submitted_at'        => 'datetime',
        'acknowledged_at'     => 'datetime',
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
}

