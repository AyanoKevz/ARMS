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

    /**
     * Working days before the first training day that a submission stays open
     * to amendment — by a Report of Changes, or by being called off entirely.
     *
     * Both windows close together on purpose: past this point the evaluators
     * have committed to the training as filed, and a change of any kind is a
     * conversation rather than a form.
     */
    public const CHANGE_WINDOW_WORKING_DAYS = 3;

    /**
     * Statuses a Notice of Cancellation may be filed against.
     *
     * A training is live from the moment it is filed, whether or not an
     * evaluator has got to it yet, so all three can still be called off. What
     * is already cancelled, rejected or never submitted cannot.
     */
    public const CANCELLABLE_STATUSES = ['submitted', 'acknowledged', 'report_changes'];

    /** Training mode code for a training held partly in a venue and partly online. */
    public const MODE_BLENDED = 'BLENDED';

    protected $fillable = [
        'accreditation_id',
        'ntc_training_type_id',
        'ntc_training_mode_id',
        'venue',
        'meeting_link',
        'training_start_date',
        'training_end_date',
        'status',
        'submitted_at',
        'acknowledged_at',
        'acknowledged_by',
        'remarks',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'previous_details',
        'ptr_reminder_sent_at',
        'ptr_overdue_notified_at',
    ];

    protected $casts = [
        'training_start_date'     => 'date',
        'training_end_date'       => 'date',
        'submitted_at'            => 'datetime',
        'acknowledged_at'         => 'datetime',
        'cancelled_at'            => 'datetime',
        'previous_details'        => 'array',
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

    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function postTrainingReport()
    {
        return $this->hasOne(PostTrainingReport::class);
    }

    /**
     * Work in progress on the post training report, if any.
     *
     * Separate from postTrainingReport because a draft is not a submission:
     * it has not been filed, the evaluator cannot see it, and it disappears
     * the moment the real report is created.
     */
    public function postTrainingDraft()
    {
        return $this->hasOne(PostTrainingDraft::class);
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
     * The last day this submission stays open to amendment.
     *
     * Three working days before the first training day. Weekends do not count
     * towards them — the training itself may fall on one, but the evaluators
     * who have to act on a change are not in on a Saturday.
     *
     * Both the Report of Changes and the Notice of Cancellation close on this
     * date; reportChangesDeadlineDate() is kept as the name the views already
     * call it by.
     */
    public function changeWindowDeadlineDate(): ?Carbon
    {
        if (!$this->training_start_date) {
            return null;
        }

        $cursor  = $this->training_start_date->copy()->startOfDay()->subDay();
        $counted = 0;

        while ($counted < self::CHANGE_WINDOW_WORKING_DAYS) {
            if (!$cursor->isWeekend()) {
                $counted++;
            }
            if ($counted < self::CHANGE_WINDOW_WORKING_DAYS) {
                $cursor->subDay();
            }
        }

        return $cursor;
    }

    /** @deprecated Use changeWindowDeadlineDate(); kept for the views that name it. */
    public function reportChangesDeadlineDate(): ?Carbon
    {
        return $this->changeWindowDeadlineDate();
    }

    /** Is the amendment window still open? */
    public function withinChangeWindow(): bool
    {
        $deadline = $this->changeWindowDeadlineDate();

        return $deadline !== null && Carbon::today()->lessThanOrEqualTo($deadline);
    }

    /**
     * Check if the report of changes can be submitted (at least 3 working days before training_start_date).
     */
    public function canSubmitReportChanges(): bool
    {
        return $this->withinChangeWindow();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * May the FATPro still call this training off?
     *
     * Same window as a Report of Changes, and for the same reason: once the
     * evaluators are three working days out they are committed to it. A
     * cancellation needs no acknowledgement, so this is the only gate there is.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES, true)
            && $this->withinChangeWindow();
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
     * The last date a training may still run on, given its first date.
     *
     * Every training type has to be completed within one month of starting:
     * one that starts on October 1 must end by October 31, and cannot reach
     * November 1. Where the next month is too short to have the same day
     * (January 31 → February), the month is clamped rather than spilling over
     * into March.
     */
    public static function latestAllowedEndDate(Carbon $firstDate): Carbon
    {
        return $firstDate->copy()->startOfDay()->addMonthNoOverflow()->subDay();
    }

    /**
     * The message given when a training runs past latestAllowedEndDate().
     */
    public static function oneMonthLimitMessage(Carbon $firstDate): string
    {
        return 'A training must be completed within one month. Starting '
            . $firstDate->format('F d, Y') . ', the last training date can be no later than '
            . self::latestAllowedEndDate($firstDate)->format('F d, Y') . '.';
    }

    /**
     * Validation rules for where a training is held.
     *
     * Every training has a venue. A blended one is also partly online, so it
     * needs the meeting link on top of the venue — not instead of it.
     */
    public static function locationRules($modeId): array
    {
        $blended = self::isBlendedModeId($modeId);

        return [
            'venue'        => ['required', 'string', 'max:500'],
            'meeting_link' => $blended
                ? ['required', 'url', 'max:500']
                : ['nullable', 'string', 'max:500'],
        ];
    }

    public const LOCATION_MESSAGES = [
        'venue.required'        => 'The venue is required.',
        'meeting_link.required' => 'A blended training needs a Zoom / meeting link.',
        'meeting_link.url'      => 'The meeting link must be a full link, starting with https://.',
    ];

    /** Is this training mode id the blended one, which also needs a meeting link? */
    public static function isBlendedModeId($modeId): bool
    {
        return NtcTrainingMode::whereKey($modeId)->value('code') === self::MODE_BLENDED;
    }

    public function isBlended(): bool
    {
        return optional($this->trainingMode)->code === self::MODE_BLENDED;
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

    /**
     * The details a Report of Changes or an evaluator's correction can touch,
     * as a reader would see them.
     *
     * Taken before and after a change and compared, so what is shown names
     * what actually moved rather than claiming the whole submission changed.
     *
     * @return array<string, string>
     */
    public function detailsSnapshot(): array
    {
        $this->loadMissing(['trainingType', 'trainingMode', 'trainingDates', 'instructors']);

        return [
            'Training Type' => $this->trainingType->name ?? '—',
            'Mode'          => $this->trainingMode->name ?? '—',
            'Venue'         => (string) $this->venue,
            'Meeting Link'  => (string) $this->meeting_link,
            'Training Days' => $this->trainingPeriodLabel(),
            'Instructors'   => $this->instructors->map->fullName()->implode(', '),
        ];
    }

    /**
     * A pending Report of Changes laid against what was acknowledged before it.
     *
     * Empty unless one is awaiting evaluation: the earlier details are dropped
     * once the changes are acknowledged, and a cancelled training has nothing
     * left to compare. Rows blank on both sides — a meeting link on a training
     * that was never blended — are left out.
     *
     * @return array<string, array{from: string, to: string, changed: bool}>
     */
    public function reportedChanges(): array
    {
        if ($this->status !== 'report_changes' || empty($this->previous_details)) {
            return [];
        }

        $rows = [];

        foreach ($this->detailsSnapshot() as $field => $to) {
            $from = (string) ($this->previous_details[$field] ?? '');

            if ($from === '' && $to === '') {
                continue;
            }

            $rows[$field] = ['from' => $from, 'to' => $to, 'changed' => $from !== $to];
        }

        return $rows;
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
     * The FATPro's other trainings a catch-up participant in this one may have
     * started in — the "already done" entries of My Training Submissions.
     *
     * Any of this FATPro's trainings, of any type, that is acknowledged (not
     * cancelled or still under review) and has already been held. This
     * training itself is never offered. Most recent first.
     *
     * @return \Illuminate\Support\Collection<int, NtcReport>
     */
    public function catchUpSources()
    {
        $userId = $this->accreditation?->user_id;

        if (!$userId) {
            return collect();
        }

        return self::query()
            ->with('trainingType')
            ->whereHas('accreditation', fn ($q) => $q->where('user_id', $userId))
            ->whereKeyNot($this->id)
            ->where('status', 'acknowledged')
            ->whereDate('training_end_date', '<=', Carbon::today())
            ->orderByDesc('training_start_date')
            ->get();
    }

    /**
     * The catch-up choices for this training, as the submission dialog reads
     * them: id, the start date it fills in, and a label to pick from — the
     * date first, since that is what is being chosen, then which training.
     *
     * @return array<int, array{id: int, start: string, label: string}>
     */
    public function catchUpOptions(): array
    {
        return $this->catchUpSources()
            ->map(fn (NtcReport $source) => [
                'id'    => $source->id,
                'start' => $source->training_start_date->format('M d, Y'),
                'label' => $source->training_start_date->format('M d, Y')
                    . ' — ' . $source->reference_number
                    . ($source->trainingType?->code ? ' (' . $source->trainingType->code . ')' : ''),
            ])
            ->values()
            ->all();
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

