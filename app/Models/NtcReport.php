<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class NtcReport extends Model
{
    /**
     * How many days each training runs, keyed by training type code.
     *
     * Calendar days, not working days: training may be held on a weekend and
     * the days need not be consecutive. The number says only how many dates the
     * NTC must carry — which dates they are is the FATPro's choice, recorded one
     * row per day in ntc_training_dates. The start date is Day 1, so a type of N
     * asks the FATPro for N-1 further dates and nothing at all when N is 1.
     */
    public const TRAINING_DURATION_DAYS = [
        'EFA' => 1,
        'OFA' => 2,
        'SFA' => 4,
    ];

    /** Fallback when a training type code is not in the table above. */
    public const TRAINING_DURATION_DAYS_DEFAULT = 1;

    /**
     * Contact hours in one training day.
     *
     * A course is prescribed in hours — EFA 8, OFA 16, SFA 32 — so that is how
     * the type selector names it. Days remain what the form actually collects,
     * since hours cannot be put in a calendar.
     */
    public const HOURS_PER_TRAINING_DAY = 8;

    /**
     * Working days a FATPro gets to file the post training report once the
     * training ends. One figure for every training type.
     */
    public const POST_TRAINING_DEADLINE_DAYS = 5;

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
    ];

    protected $casts = [
        'training_start_date'     => 'date',
        'training_end_date'       => 'date',
        'submitted_at'            => 'datetime',
        'acknowledged_at'         => 'datetime',
        'ptr_reminder_sent_at'    => 'datetime',
        'ptr_overdue_notified_at' => 'datetime',
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

    /**
     * Every day this training runs on, earliest first.
     */
    public function trainingDates()
    {
        return $this->hasMany(NtcTrainingDate::class)->orderBy('training_date');
    }

    /**
     * The instructors the FATPro declared would conduct this training.
     *
     * People rather than their per-application `instructors` rows: those are
     * replaced on every renewal, which would leave an older training pointing
     * at a roster entry the FATPro no longer appears to have.
     */
    public function instructors()
    {
        return $this->belongsToMany(
            InstructorPerson::class,
            'ntc_report_instructor',
            'ntc_report_id',
            'instructor_person_id'
        )
            ->withTimestamps()
            ->orderBy('last_name')
            ->orderBy('first_name');
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

    /**
     * Has the first training day arrived?
     *
     * True on the day itself, not the morning after: once a training is under
     * way its declared details are a record of what was approved, not a plan
     * still open to amendment.
     */
    public function hasTrainingStarted(): bool
    {
        return $this->training_start_date
            && Carbon::today()->greaterThanOrEqualTo($this->training_start_date->copy()->startOfDay());
    }

    /**
     * May a Training Evaluator still correct this submission's details?
     *
     * Only once it has been acknowledged — before that the FATPro is still
     * being asked for changes through the ordinary evaluation — and only while
     * the training has yet to begin.
     */
    public function detailsAreEditable(): bool
    {
        return $this->status === 'acknowledged' && !$this->hasTrainingStarted();
    }

    // ── Training duration ─────────────────────────────────────────────────────

    /**
     * How many days this training runs, from its type.
     */
    public function trainingDurationDays(): int
    {
        return self::durationDaysForCode($this->trainingType->code ?? '');
    }

    /**
     * Number of training days for a type code (EFA 1 / OFA 2 / SFA 4).
     */
    public static function durationDaysForCode(?string $code): int
    {
        return self::TRAINING_DURATION_DAYS[strtoupper((string) $code)]
            ?? self::TRAINING_DURATION_DAYS_DEFAULT;
    }

    /**
     * Replace this report's training days and re-derive its start and end.
     *
     * Takes the dates already grouped by the day of the course they belong to,
     * because one curriculum day may be delivered over several dates and the
     * grouping cannot be recovered from the dates alone.
     *
     * training_start_date and training_end_date are written from the MIN and
     * MAX across every group, which is what keeps the deadlines elsewhere (the
     * report-of-changes window, the post training deadline,
     * hasTrainingConcluded) reading two plain columns and knowing nothing about
     * this table.
     *
     * Caller is responsible for having validated the groups against the
     * training type; this trusts what it is given and simply records it.
     *
     * @param  array<int, array<int, string|\Carbon\Carbon>>  $datesByDay
     */
    public function syncTrainingDates(array $datesByDay): void
    {
        $rows = [];
        $all  = collect();

        foreach ($datesByDay as $dayNo => $dates) {
            foreach ((array) $dates as $date) {
                $parsed = Carbon::parse($date)->startOfDay();
                $all->push($parsed);

                $rows[] = [
                    'ntc_report_id' => $this->id,
                    'training_date' => $parsed->toDateString(),
                    'day_no'        => (int) $dayNo,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }
        }

        if (!$rows) {
            return;
        }

        $this->trainingDates()->delete();
        NtcTrainingDate::insert($rows);

        $sorted = $all->sort()->values();

        $this->forceFill([
            'training_start_date' => $sorted->first()->toDateString(),
            'training_end_date'   => $sorted->last()->toDateString(),
        ])->save();

        $this->unsetRelation('trainingDates');
    }

    /**
     * The training dates grouped by the day of the course they belong to.
     *
     * Falls back to the start date as a lone Day 1 for a report written before
     * the day rows existed, so a caller never has to handle an empty map.
     *
     * @return array<int, array<int, string>>
     */
    public function trainingDatesByDay(): array
    {
        $grouped = [];

        foreach ($this->trainingDates as $row) {
            $grouped[$row->day_no][] = $row->training_date->toDateString();
        }

        if (!$grouped && $this->training_start_date) {
            $grouped[1] = [$this->training_start_date->toDateString()];
        }

        ksort($grouped);

        foreach ($grouped as $dayNo => $dates) {
            sort($dates);
            $grouped[$dayNo] = $dates;
        }

        return $grouped;
    }

    /**
     * The training days as Y-m-d strings, earliest first.
     *
     * Falls back to the start date alone for a report written before the day
     * rows existed, so a caller never has to handle an empty list.
     *
     * @return array<int, string>
     */
    public function trainingDayList(): array
    {
        $dates = $this->trainingDates->pluck('training_date')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        if ($dates) {
            return $dates;
        }

        return $this->training_start_date
            ? [$this->training_start_date->toDateString()]
            : [];
    }

    /**
     * The training days as a human would state them.
     *
     * A plain "start — end" reads as a continuous block, which it no longer
     * has to be: days may skip weekends, or skip about entirely. Consecutive
     * runs still collapse to a range, because spelling out four adjacent dates
     * is noise; anything with a gap is listed in full so the reader is not
     * misled about which days the training actually ran.
     */
    public function trainingPeriodLabel(): string
    {
        return implode(' ', $this->trainingPeriodSegments());
    }

    /**
     * The same label as its separate pieces: dates and the separators between
     * them, in order.
     *
     * Backs trainingPeriodLabel(), and exists separately for any caller that
     * has to lay the dates out itself — a narrow column must break between
     * dates and never inside one, since "September 05," on one line and
     * "2026" on the next reads as a different date, and CSS cannot pick that
     * break point on its own.
     *
     * @return array<int, string>
     */
    public function trainingPeriodSegments(): array
    {
        $days = collect($this->trainingDayList())->map(fn ($date) => Carbon::parse($date));

        if ($days->isEmpty()) {
            return ['N/A'];
        }

        if ($days->count() === 1) {
            return [$days->first()->format('F d, Y')];
        }

        // Cast: Carbon returns a float here, so a bare === against the int
        // count would never hold and every training would read as a list.
        $spansExactly = (int) $days->first()->diffInDays($days->last()) === $days->count() - 1;

        if ($spansExactly) {
            return [
                $days->first()->format('F d, Y'),
                '—',
                $days->last()->format('F d, Y'),
            ];
        }

        $segments = [];

        foreach ($days as $index => $date) {
            if ($index > 0) {
                $segments[] = '·';
            }

            $segments[] = $date->format('M d, Y');
        }

        return $segments;
    }

    // ── Post Training Report ──────────────────────────────────────────────────

    /**
     * Working days allowed to file the post training report.
     *
     * Flat for every training type — the type governs how long the training
     * runs, not how long the FATPro has to report on it.
     */
    public function postTrainingDaysAllowed(): int
    {
        return self::POST_TRAINING_DEADLINE_DAYS;
    }

    /**
     * The last day the post training report may be submitted: five working days
     * after the training ends.
     */
    public function postTrainingDeadlineDate(): ?Carbon
    {
        if (!$this->training_end_date) {
            return null;
        }

        return self::addWorkingDays(
            $this->training_end_date->copy()->startOfDay(),
            self::POST_TRAINING_DEADLINE_DAYS
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

