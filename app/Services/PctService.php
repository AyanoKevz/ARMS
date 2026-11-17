<?php

namespace App\Services;

use App\Models\Application;
use App\Models\PctEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class PctService
{
    /**
     * PCT Step Definitions.
     * [step_number => [name, target_days]]
     */
    public const STEPS = [
        1 => ['name' => 'Submission',                  'target_days' => 3],
        2 => ['name' => 'Receipt of Requirements',     'target_days' => 1],
        3 => ['name' => 'Evaluation',                  'target_days' => 5],
        4 => ['name' => 'Pending Interview',           'target_days' => 3],
        5 => ['name' => 'Interview',                   'target_days' => 1],
        6 => ['name' => 'Interview Result',            'target_days' => 1],
        7 => ['name' => 'Recommendation & Payment',    'target_days' => 5],
        8 => ['name' => 'Certificate Issuance',        'target_days' => 1],
    ];

    public const TOTAL_TARGET_DAYS = 20;

    /**
     * OSH Practitioner steps (Accreditation division).
     *
     * As with FATPro, nothing runs while the application is pending. Steps 1
     * and 2 are recorded as done when an admin starts the evaluation (Step 3).
     * Step 4 is the applicant choosing an interview slot — not admin time, so
     * it is recorded as done the moment the approvals are saved. Step 5 is
     * the panel interview: it waits (paused) from the booking until the slot
     * starts and runs at most 2 hours. Step 6 runs from the interview's stop
     * through the result and the recommendation form, until the scanned
     * signed recommendation is uploaded. Step 7 (Payment), as FATPro's
     * payment stage: paused while the applicant has yet to pay, running once
     * the proof of payment is in, completed when the payment is validated.
     * Step 8 runs from the validation (the accreditation number is issued)
     * until both the signed certificate and the ID card are uploaded; Step 9
     * until they are released to the applicant.
     *
     * Step 8 continues Step 6 ('continues' => 6): its clock picks up at the
     * time Step 6 ended with, and its 4 days 4 hours is the two steps'
     * combined target (Step 6's 3 days 4 hours + 1 day). It therefore adds
     * only its own time to the total elapsed, and 1 day to the total target.
     *
     * target_seconds, where given, is the exact target in working seconds
     * (a working day is 9 hours, 32,400 seconds); target_days is the whole
     * number kept on the pct_entries row.
     */
    public const PRACTITIONER_STEPS = [
        1 => ['name' => 'Acknowledge Receipt of Application',              'target_days' => 1],
        2 => ['name' => 'Acknowledge Advice from OED Accreditation Focal', 'target_days' => 2],
        3 => ['name' => 'Evaluation of Submitted Documents',               'target_days' => 5],
        4 => ['name' => 'Set up Interview Schedule',                       'target_days' => 3],
        5 => ['name' => 'Panel Interview',                                 'target_days' => 1, 'target_seconds' => 7200,   'target_label' => '2 hours'],
        6 => ['name' => 'Interview Result and Recommendation',             'target_days' => 4, 'target_seconds' => 111600, 'target_label' => '3 days 4 hours'],
        7 => ['name' => 'Payment',                                         'target_days' => 3, 'target_seconds' => 77400,  'target_label' => '2 days 3 hours 30 minutes'],
        8 => ['name' => 'Prepare the Certificate of Accreditation and Identification Card', 'target_days' => 5, 'target_seconds' => 144000, 'target_label' => '4 days 4 hours', 'continues' => 6],
        9 => ['name' => 'Release of Certificate and ID',                   'target_days' => 1, 'target_seconds' => 3600,   'target_label' => '1 hour'],
    ];
    public const MAX_INTERVIEW_SECONDS = 14400; // 4 hours in seconds (4 * 3600)
    public const MAX_PRACTITIONER_INTERVIEW_SECONDS = 7200; // 2 hours

    /** Working seconds in one PCT day (8:00 AM – 5:00 PM). */
    public const WORKING_DAY_SECONDS = 32400;

    /** A step's target in working seconds. */
    public static function targetSeconds(array $stepDef): int
    {
        return (int) ($stepDef['target_seconds'] ?? $stepDef['target_days'] * self::WORKING_DAY_SECONDS);
    }

    /**
     * What a step adds to the overall target: its own target, less the
     * target of the step it continues (Step 8 adds 1 day to Step 6's).
     *
     * @param array<int, array<string, mixed>> $stepDefs
     */
    public static function addedTargetSeconds(array $stepDefs, int $stepNumber): int
    {
        $def       = $stepDefs[$stepNumber];
        $continues = $def['continues'] ?? null;

        return self::targetSeconds($def) - ($continues ? self::targetSeconds($stepDefs[$continues]) : 0);
    }

    /** "5 days", "2 hours", "3 days 4 hours". */
    public static function targetLabel(array $stepDef): string
    {
        return $stepDef['target_label'] ?? $stepDef['target_days'] . ' ' . \Illuminate\Support\Str::plural('day', $stepDef['target_days']);
    }

    /** How long an interview may run before it is stopped automatically. */
    public function maxInterviewSeconds(Application $application): int
    {
        return $application->isPractitioner() ? self::MAX_PRACTITIONER_INTERVIEW_SECONDS : self::MAX_INTERVIEW_SECONDS;
    }

    /**
     * Advance a date by working days — weekdays that are not holidays — the
     * way PCT counts them.
     */
    public static function addWorkingDays(Carbon $from, int $workingDays): Carbon
    {
        $cursor   = $from->copy();
        $holidays = self::getHolidays($from->year, $from->year + 1);
        $counted  = 0;

        while ($counted < $workingDays) {
            $cursor->addDay();
            if (! $cursor->isWeekend() && ! in_array($cursor->format('Y-m-d'), $holidays, true)) {
                $counted++;
            }
        }

        return $cursor;
    }

    /**
     * Steps that exist only to cover the interview stage. Applications that skip
     * the interview (renewals — see Application::skipsInterview()) never get an
     * entry for these, and their target days drop out of the overall SLA.
     */
    public const INTERVIEW_STEPS = [4, 5, 6];

    /**
     * Initialize PCT when an application is first moved to evaluation.
     * Auto-completes Steps 1 (Submission) and 2 (Receipt of Requirements)
     * since these are system-automatic steps.
     */
    /**
     * The step definitions that apply to one application.
     *
     * @return array<int, array{name: string, target_days: int}>
     */
    public function stepsFor(Application $application): array
    {
        return $application->isPractitioner() ? self::PRACTITIONER_STEPS : self::STEPS;
    }

    /**
     * Practitioner evaluation start — the same as FATPro's
     * initializeFromEvaluation(): no clock runs while the application is
     * pending. Steps 1 (Acknowledge Receipt) and 2 (Acknowledge Advice from
     * the OED Accreditation Focal) are recorded as done with no time
     * counted, and Step 3 (Evaluation) starts now.
     */
    public function startPractitionerEvaluation(Application $application): void
    {
        if ($application->pctEntries()->where('step_number', 3)->exists()) {
            return;
        }

        // Entries left by the earlier flow, which ran Step 2 from submission.
        $application->pctEntries()->delete();

        $submittedAt = $application->submitted_at ?? $application->created_at ?? Carbon::now();

        $this->createCompletedStep($application, 1, $submittedAt);
        PctEntry::create([
            'application_id'  => $application->id,
            'step_name'       => self::PRACTITIONER_STEPS[2]['name'],
            'step_number'     => 2,
            'target_days'     => self::PRACTITIONER_STEPS[2]['target_days'],
            'started_at'      => $submittedAt,
            'completed_at'    => Carbon::now(),
            'elapsed_seconds' => 0,
            'is_active'       => false,
        ]);

        $this->startStep($application, 3);
        $application->unsetRelation('pctEntries');
    }

    /**
     * Practitioner approvals saved: Step 3 (Evaluation) completes, and Step 4
     * (Set up Interview Schedule) is recorded as done with no time counted —
     * the applicant books the slot themselves.
     */
    public function completePractitionerEvaluation(Application $application): void
    {
        $application->pctEntries()->where('step_number', 3)->whereNull('completed_at')->first()?->complete();

        if (! $application->pctEntries()->where('step_number', 4)->exists()) {
            $this->createCompletedStep($application, 4, Carbon::now());
        }

        $application->unsetRelation('pctEntries');
    }

    /**
     * Practitioner interview booked: Step 5 (Panel Interview) is opened and
     * paused straight away, as FATPro's scheduleInterview() does. It resumes
     * on its own once the slot's start time arrives, or when an admin
     * presses Start Interview.
     */
    public function schedulePractitionerInterview(Application $application): void
    {
        if ($application->pctEntries()->where('step_number', 5)->exists()) {
            return;
        }

        $this->startStep($application, 5)->pause();
        $application->unsetRelation('pctEntries');
        $application->unsetRelation('activePctEntry');
    }

    /**
     * Practitioner interview stopped: Step 5 completes and Step 6 (Interview
     * Result and Recommendation) starts, as FATPro's Interview Result does.
     */
    public function startPractitionerInterviewResult(Application $application): void
    {
        $application->pctEntries()->where('step_number', 5)->whereNull('completed_at')->first()?->complete();

        if (! $application->pctEntries()->where('step_number', 6)->exists()) {
            $this->startStep($application, 6);
        }

        $application->unsetRelation('pctEntries');
        $application->unsetRelation('activePctEntry');
    }

    /**
     * Scanned signed recommendation uploaded: Step 6 completes and Step 7
     * (Payment) opens paused — the applicant has yet to pay. It resumes when
     * they upload the proof of payment (TrackingController::submitPaymentPublic).
     */
    public function startPractitionerPayment(Application $application): void
    {
        $application->pctEntries()->where('step_number', 6)->whereNull('completed_at')->first()?->complete();

        if (! $application->pctEntries()->where('step_number', 7)->exists()) {
            $this->startStep($application, 7)->pause();
        }

        $application->unsetRelation('pctEntries');
        $application->unsetRelation('activePctEntry');
    }

    /**
     * Payment validated, accreditation number issued: Step 7 completes and
     * Step 8 (Prepare the Certificate and ID Card) starts.
     */
    public function startPractitionerCertificate(Application $application): void
    {
        $this->advancePractitionerStep($application, 7, 8);
    }

    /** Signed certificate and ID card both uploaded: Step 8 completes, Step 9 (Release) starts. */
    public function startPractitionerRelease(Application $application): void
    {
        $this->advancePractitionerStep($application, 8, 9);
    }

    /** Certificate and ID card released: Step 9 completes — the end of the cycle. */
    public function completePractitionerRelease(Application $application): void
    {
        $application->pctEntries()->where('step_number', 9)->whereNull('completed_at')->first()?->complete();
        $application->unsetRelation('pctEntries');
        $application->unsetRelation('activePctEntry');
    }

    private function advancePractitionerStep(Application $application, int $from, int $to): void
    {
        $application->pctEntries()->where('step_number', $from)->whereNull('completed_at')->first()?->complete();

        if (! $application->pctEntries()->where('step_number', $to)->exists()) {
            $this->startStep($application, $to);
        }

        $application->unsetRelation('pctEntries');
        $application->unsetRelation('activePctEntry');
    }

    public function initializeFromEvaluation(Application $application): void
    {
        // Guard: don't re-initialize if already has PCT entries
        if ($application->pctEntries()->exists()) {
            return;
        }

        $now = Carbon::now();
        $submittedAt = $application->submitted_at ?? $application->created_at ?? $now;

        // Step 1: Submission (auto-completed, 0 admin time — system step)
        PctEntry::create([
            'application_id' => $application->id,
            'step_name'      => self::STEPS[1]['name'],
            'step_number'    => 1,
            'target_days'    => self::STEPS[1]['target_days'],
            'started_at'     => $submittedAt,
            'completed_at'   => $submittedAt,
            'elapsed_seconds' => 0,
            'is_active'      => false,
        ]);

        // Step 2: Receipt of Requirements (auto-completed, 0 admin time — system step)
        PctEntry::create([
            'application_id' => $application->id,
            'step_name'      => self::STEPS[2]['name'],
            'step_number'    => 2,
            'target_days'    => self::STEPS[2]['target_days'],
            'started_at'     => $submittedAt,
            'completed_at'   => $now,
            'elapsed_seconds' => 0,
            'is_active'      => false,
        ]);

        // Step 3: Evaluation — starts NOW (admin is evaluating)
        $this->startStep($application, 3);
    }

    /**
     * Create a pre-completed step for historical catching-up.
     */
    private function createCompletedStep(Application $application, int $stepNumber, Carbon $time): void
    {
        $stepDef = $this->stepsFor($application)[$stepNumber] ?? ['name' => "Step {$stepNumber}", 'target_days' => 1];
        PctEntry::create([
            'application_id' => $application->id,
            'step_name'      => $stepDef['name'],
            'step_number'    => $stepNumber,
            'target_days'    => $stepDef['target_days'],
            'started_at'     => $time,
            'completed_at'   => $time,
            'elapsed_seconds' => 0,
            'is_active'      => false,
        ]);
    }

    /**
     * Clean up duplicate PCT entries for the same step.
     */
    public function cleanupDuplicateEntries(Application $application): void
    {
        $allEntries = $application->relationLoaded('pctEntries')
            ? $application->pctEntries
            : $application->pctEntries()->get();

        if ($allEntries->isEmpty()) {
            return;
        }

        // Fast check: if count matches unique step_number count, no duplicates exist
        if ($allEntries->pluck('step_number')->count() === $allEntries->pluck('step_number')->unique()->count()) {
            return;
        }

        $groups = $allEntries->groupBy('step_number');

        $hasDeleted = false;
        foreach ($groups as $stepNumber => $entries) {
            if ($entries->count() > 1) {
                // Keep the latest one, delete the rest
                $sorted = $entries->sortByDesc('id');
                $keep = $sorted->first();
                foreach ($sorted as $entry) {
                    if ($entry->id !== $keep->id) {
                        $entry->delete();
                        $hasDeleted = true;
                    }
                }
            }
        }

        if ($hasDeleted) {
            $application->unsetRelation('pctEntries');
        }
    }

    /**
     * Auto-initialize missing PCT entries for historical or skipped applications.
     */
    public function initializeMissingEntries(Application $application): void
    {
        $hasEntries = $application->relationLoaded('pctEntries')
            ? $application->pctEntries->isNotEmpty()
            : $application->pctEntries()->exists();

        if ($hasEntries) {
            return;
        }

        // Practitioner PCT starts with the evaluation, as FATPro's does. A
        // pending one has no PCT yet.
        if ($application->isPractitioner()) {
            $statusName = $application->latestStatus?->status?->name;
            $afterInterview = ['For Recommendation', 'Awaiting Payment', 'Payment Verification', 'Approved'];
            if (in_array($statusName, ['Under Evaluation', 'For Update', 'Scheduled for Interview', ...$afterInterview], true)) {
                $this->startPractitionerEvaluation($application);
                if ($statusName === 'For Update') {
                    $application->pctEntries()->where('step_number', 3)->first()?->pause();
                } else {
                    if ($statusName !== 'Under Evaluation') {
                        $this->completePractitionerEvaluation($application);
                    }
                    if ($statusName === 'Scheduled for Interview' && $application->interview()->exists()) {
                        $this->schedulePractitionerInterview($application);
                    } elseif (in_array($statusName, $afterInterview, true)) {
                        $this->createCompletedStep($application, 5, Carbon::now());
                        $this->startPractitionerInterviewResult($application);
                        if ($statusName !== 'For Recommendation') {
                            $this->startPractitionerPayment($application);
                            if ($statusName === 'Payment Verification') {
                                $this->resumeCurrentStep($application);
                            }
                        }
                        if ($statusName === 'Approved') {
                            $this->resumeCurrentStep($application);
                            $this->startPractitionerCertificate($application);
                            $accreditation = $application->accreditation()->first();
                            if ($accreditation?->scanned_certificate && $accreditation->id_card) {
                                $this->startPractitionerRelease($application);
                                if ($accreditation->released_at) {
                                    $this->completePractitionerRelease($application);
                                }
                            }
                        }
                    }
                }
            }

            $application->unsetRelation('pctEntries');
            return;
        }

        // ── Clean up any duplicate entries for the same step ──
        $this->cleanupDuplicateEntries($application);

        $now = Carbon::now();
        $submittedAt = $application->submitted_at ?? $application->created_at ?? $now;
        $statusName = $application->latestStatus?->status?->name;

        if (!$statusName || $statusName === 'Submitted') {
            return; // Not yet in evaluation
        }

        // Step 1 & 2 are always completed
        $this->createCompletedStep($application, 1, $submittedAt);
        $this->createCompletedStep($application, 2, $submittedAt);

        if (in_array($statusName, ['Under Evaluation', 'For Update'])) {
            $step3 = $this->startStep($application, 3);
            if ($statusName === 'For Update') {
                $step3->pause();
            }
            return;
        }

        // Complete Step 3
        $this->createCompletedStep($application, 3, $now);

        if ($statusName === 'Scheduled for Interview') {
            if ($application->interview) {
                $this->createCompletedStep($application, 4, $now);
                $step5 = $this->startStep($application, 5);
                // Pause it because interview is scheduled but not necessarily started yet
                $step5->pause();
            } else {
                $this->startStep($application, 4);
            }
            return;
        }

        // Complete Step 4, 5, 6 — unless this application never had an interview
        // stage to begin with, in which case they stay absent (rendered "skipped").
        if (!$application->skipsInterview()) {
            $this->createCompletedStep($application, 4, $now);
            $this->createCompletedStep($application, 5, $now);
            $this->createCompletedStep($application, 6, $now);
        }

        if (in_array($statusName, ['Awaiting Payment', 'Payment Verification'])) {
            $this->startStep($application, 7);
            return;
        }

        // Complete Step 7
        $this->createCompletedStep($application, 7, $now);

        // Step 8: Certificate Issuance
        $this->createCompletedStep($application, 8, $now);
    }

    /**
     * Start a specific PCT step for an application.
     */
    public function startStep(Application $application, int $stepNumber): PctEntry
    {
        $stepDef = $this->stepsFor($application)[$stepNumber] ?? ['name' => "Step {$stepNumber}", 'target_days' => 1];

        $newStep = PctEntry::create([
            'application_id' => $application->id,
            'step_name'      => $stepDef['name'],
            'step_number'    => $stepNumber,
            'target_days'    => $stepDef['target_days'],
            'started_at'     => Carbon::now(),
            'is_active'      => true,
            'elapsed_seconds' => 0,
        ]);

        $application->unsetRelation('pctEntries');
        return $newStep;
    }

    /**
     * Complete the currently active step for an application.
     */
    public function completeCurrentStep(Application $application): ?PctEntry
    {
        $active = $application->pctEntries()->active()->first();
        if ($active) {
            $active->complete();
            $application->unsetRelation('pctEntries');
        }
        return $active;
    }

    /**
     * Complete the current step and immediately start the next one.
     */
    public function transitionToStep(Application $application, int $nextStepNumber): PctEntry
    {
        // Guard: if the next step is already active/paused, just complete the current one (if different) and return it
        $existing = $application->pctEntries()
            ->where('step_number', $nextStepNumber)
            ->whereNull('completed_at')
            ->first();

        if ($existing) {
            $active = $application->pctEntries()->active()->first();
            if ($active && $active->step_number !== $nextStepNumber) {
                $active->complete();
            }
            return $existing;
        }

        $this->completeCurrentStep($application);
        return $this->startStep($application, $nextStepNumber);
    }

    /**
     * Pause the currently active step (waiting for applicant action).
     */
    public function pauseCurrentStep(Application $application): ?PctEntry
    {
        $active = $application->pctEntries()->active()->first();
        if ($active) {
            $active->pause();
        }
        return $active;
    }

    /**
     * Resume the currently paused step (applicant has submitted back).
     */
    public function resumeCurrentStep(Application $application): ?PctEntry
    {
        // Find the paused entry (active but with paused_at set)
        $paused = $application->pctEntries()
            ->whereNotNull('paused_at')
            ->whereNull('completed_at')
            ->first();

        if ($paused) {
            $paused->resume();
        }
        return $paused;
    }

    /**
     * Complete ALL active/paused steps (used when application is rejected/archived).
     */
    public function completeAllSteps(Application $application): void
    {
        $activeEntries = $application->pctEntries()
            ->whereNull('completed_at')
            ->get();

        foreach ($activeEntries as $entry) {
            $entry->complete();
        }
    }

    /**
     * Calculate total elapsed working days for an application.
     * Excludes weekends (Sat/Sun) and Philippine public holidays from the count.
     */
    public static function calculateWorkingDays(Carbon $start, Carbon $end): float
    {
        if ($start->greaterThanOrEqualTo($end)) {
            return 0;
        }

        $totalSeconds = 0;
        $current = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        // Get Philippine holidays for the relevant year range
        $holidays = self::getHolidays($start->year, $end->year);

        while ($current->lessThanOrEqualTo($endDay)) {
            $isWeekend = $current->isWeekend();
            $isHoliday = in_array($current->format('Y-m-d'), $holidays);

            if (!$isWeekend && !$isHoliday) {
                // Working day — calculate how many seconds of this day fall within [start, end]
                $dayStart = $current->copy()->startOfDay();
                $dayEnd   = $current->copy()->endOfDay();

                $effectiveStart = $start->greaterThan($dayStart) ? $start : $dayStart;
                $effectiveEnd   = $end->lessThan($dayEnd) ? $end : $dayEnd;

                if ($effectiveStart->lessThan($effectiveEnd)) {
                    $totalSeconds += $effectiveStart->diffInSeconds($effectiveEnd);
                }
            }

            $current->addDay();
        }

        return round($totalSeconds / 86400, 1);
    }

    private static array $holidayCache = [];

    /**
     * Get Philippine public holidays for the given year range.
     * Returns an array of date strings in Y-m-d format.
     */
    public static function getHolidays(int $startYear, int $endYear): array
    {
        $cacheKey = "{$startYear}_{$endYear}";
        if (isset(self::$holidayCache[$cacheKey])) {
            return self::$holidayCache[$cacheKey];
        }

        $holidays = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $holidays = array_merge($holidays, [
                // Regular Holidays
                "{$year}-01-01", // New Year's Day
                "{$year}-04-09", // Araw ng Kagitingan
                "{$year}-05-01", // Labor Day
                "{$year}-06-12", // Independence Day
                "{$year}-08-21", // Ninoy Aquino Day
                "{$year}-08-26", // National Heroes Day (approximate)
                "{$year}-11-30", // Bonifacio Day
                "{$year}-12-25", // Christmas Day
                "{$year}-12-30", // Rizal Day

                // Special Non-Working Days (common ones)
                "{$year}-02-25", // EDSA People Power Revolution
                "{$year}-11-01", // All Saints' Day
                "{$year}-11-02", // All Souls' Day
                "{$year}-12-24", // Christmas Eve
                "{$year}-12-31", // Last Day of the Year
            ]);
        }

        self::$holidayCache[$cacheKey] = $holidays;
        return $holidays;
    }

    /**
     * Calculate total elapsed working seconds for an application between start and end.
     * Only counts hours between 8:00 AM and 5:00 PM on weekdays (Mon-Fri) excluding holidays.
     */
    public static function calculateWorkingSeconds(Carbon $start, Carbon $end): int
    {
        if ($start->greaterThanOrEqualTo($end)) {
            return 0;
        }

        $totalSeconds = 0;
        
        $start = $start->copy();
        $end = $end->copy();

        $current = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        $holidays = self::getHolidays($start->year, $end->year);

        while ($current->lessThanOrEqualTo($endDay)) {
            $isWeekend = $current->isWeekend();
            $isHoliday = in_array($current->format('Y-m-d'), $holidays);

            if (!$isWeekend && !$isHoliday) {
                // Working day — calculate overlap with 8:00 AM to 5:00 PM (9 hours window)
                $workStart = $current->copy()->setTime(8, 0, 0);
                $workEnd   = $current->copy()->setTime(17, 0, 0);

                $effectiveStart = $start->greaterThan($workStart) ? $start : $workStart;
                $effectiveEnd   = $end->lessThan($workEnd) ? $end : $workEnd;

                if ($effectiveStart->lessThan($effectiveEnd)) {
                    $totalSeconds += $effectiveStart->diffInSeconds($effectiveEnd);
                }
            }

            $current->addDay();
        }

        return $totalSeconds;
    }

    /**
     * Get a summary of PCT status for display.
     */
    public function getSummary(Application $application): array
    {
        // ── Clean up any duplicate entries for the same step ──
        $this->cleanupDuplicateEntries($application);

        // ── Auto-Resume Step 5 (Interview) if scheduled time is reached or passed ──
        $this->autoResumeInterviewIfScheduled($application);

        // ── Auto-Stop Step 5 (Interview) after 4 hours ──
        $this->autoStopInterviewIfExpired($application);

        // ── Auto-Reconcile Step 7 state if signed recommendation is uploaded but payment is pending ──
        $payment = $application->relationLoaded('payment') ? $application->payment : $application->payment;
        if ($payment && !$payment->proof_of_payment && $payment->signed_recommendation_letter) {
            $entriesColl = $application->relationLoaded('pctEntries') ? $application->pctEntries : $application->pctEntries()->get();
            $active = $entriesColl->first(fn($e) => $e->is_active && $e->step_number == 7);
            if ($active && !$active->paused_at) {
                $active->pause();
            }
        }

        $entries = $application->relationLoaded('pctEntries')
            ? $application->pctEntries->sortBy('step_number')
            : $application->pctEntries()->orderBy('step_number')->get();

        $totalElapsed = 0;
        $steps = [];

        // Renewals never enter the interview stage, so Steps 4-6 have no entry.
        // Report them as explicitly skipped rather than perpetually pending, and
        // take their target days out of the overall SLA.
        $stepDefs     = $this->stepsFor($application);
        $skippedSteps = (! $application->isPractitioner() && $application->skipsInterview()) ? self::INTERVIEW_STEPS : [];
        $totalTarget  = array_sum(array_column($stepDefs, 'target_days'));
        foreach ($skippedSteps as $skippedStep) {
            $totalTarget -= $stepDefs[$skippedStep]['target_days'];
        }

        // A step with an exact target_seconds (the practitioner's 2-hour
        // interview, 3 days 4 hours recommendation) is measured in seconds;
        // the others keep their day-based figures.
        // A step that continues another (practitioner Step 8 → Step 6) adds
        // only the difference between their targets.
        $exactTargets = collect($stepDefs)->contains(fn ($def) => isset($def['target_seconds']));
        if ($exactTargets) {
            $totalTarget = round(
                array_sum(array_map(fn ($num) => self::addedTargetSeconds($stepDefs, $num), array_keys($stepDefs))) / self::WORKING_DAY_SECONDS,
                1
            );
        }

        foreach ($stepDefs as $num => $def) {
            $entry = $entries->firstWhere('step_number', $num);

            if ($entry) {
                $own = $entry->totalElapsedSeconds();
                $totalElapsed += $own;

                // A continuation's clock picks up where the step it continues
                // ended; only its own time counts towards the total.
                $continued = isset($def['continues']) ? $entries->firstWhere('step_number', $def['continues']) : null;
                $elapsed   = $own + ($continued ? $continued->totalElapsedSeconds() : 0);

                $elapsedDays = round($elapsed / 32400, 1);

                if (isset($def['target_seconds'])) {
                    $isOverdue = $elapsed > $def['target_seconds'];
                    $percent   = min(100, round(($elapsed / $def['target_seconds']) * 100));
                } else {
                    $isOverdue = $elapsedDays > $def['target_days'];
                    $percent   = $def['target_days'] > 0 ? min(100, round(($elapsedDays / $def['target_days']) * 100)) : 0;
                }

                $steps[] = [
                    'number'      => $num,
                    'name'        => $def['name'],
                    'target_days' => $def['target_days'],
                    'target_seconds' => self::targetSeconds($def),
                    'target_label'   => self::targetLabel($def),
                    'continues'      => $def['continues'] ?? null,
                    'status'      => $entry->stepStatus(),
                    'elapsed_days' => $elapsedDays,
                    'elapsed_seconds' => $elapsed,
                    'started_at'  => $entry->started_at,
                    'completed_at' => $entry->completed_at,
                    'paused_at'   => $entry->paused_at,
                    'is_overdue'  => $isOverdue,
                    'percent'     => $percent,
                ];
            } else {
                $steps[] = [
                    'number'      => $num,
                    'name'        => $def['name'],
                    'target_days' => $def['target_days'],
                    'target_seconds' => self::targetSeconds($def),
                    'target_label'   => self::targetLabel($def),
                    'continues'      => $def['continues'] ?? null,
                    'status'      => in_array($num, $skippedSteps, true) ? 'skipped' : 'pending',
                    'elapsed_days' => 0,
                    'started_at'  => null,
                    'completed_at' => null,
                    'paused_at'   => null,
                    'is_overdue'  => false,
                    'percent'     => 0,
                ];
            }
        }

        $totalDays = round($totalElapsed / 32400, 1);

        return [
            'steps'          => $steps,
            'total_elapsed'  => $totalDays,
            'total_target'   => $totalTarget,
            'percent'        => $totalTarget > 0 ? min(100, round(($totalDays / $totalTarget) * 100)) : 0,
            'is_overdue'     => $totalDays > $totalTarget,
            'has_entries'    => $entries->isNotEmpty(),
        ];
    }

    /**
     * Automatically resume Step 5 (Interview) if the scheduled date/time is reached/passed.
     */
    public function autoResumeInterviewIfScheduled(Application $application)
    {
        $entries = $application->relationLoaded('pctEntries')
            ? $application->pctEntries
            : null;

        if ($entries) {
            $active = $entries->first(fn($e) => $e->step_number == 5 && $e->is_active && $e->paused_at);
        } else {
            $active = $application->pctEntries()->where('step_number', 5)->where('is_active', true)->whereNotNull('paused_at')->first();
        }

        if ($active && $application->interview) {
            $date = $application->interview->interview_date;
            $dateStr = $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;
            $scheduledTimeStr = $dateStr . ' ' . $application->interview->interview_time;
            try {
                $scheduledTime = \Carbon\Carbon::parse($scheduledTimeStr);
                if (now()->greaterThanOrEqualTo($scheduledTime)) {
                    $active->resume();
                    
                    // Clear the cached relations so the model reads the fresh values from the DB!
                    $application->unsetRelation('pctEntries');
                    $application->unsetRelation('activePctEntry');
                }
            } catch (\Exception $e) {
                // Ignore parse errors
            }
        }
    }

    /**
     * Automatically stop Step 5 (Interview) if it has been running for 4 hours or more.
     * Transitions the application to Step 6 (Interview Result) and caps Step 5 elapsed time to 4 hours.
     */
    public function autoStopInterviewIfExpired(Application $application): bool
    {
        $entries = $application->relationLoaded('pctEntries')
            ? $application->pctEntries
            : null;

        if ($entries) {
            $active = $entries->first(fn($e) => $e->step_number == 5 && $e->is_active && !$e->paused_at && !$e->completed_at);
        } else {
            $active = $application->pctEntries()
                ->where('step_number', 5)
                ->where('is_active', true)
                ->whereNull('paused_at')
                ->whereNull('completed_at')
                ->first();
        }

        if ($active) {
            return $this->stopInterviewIfOverLimit($application, $active);
        }

        return false;
    }

    /**
     * Stop a running Step 5 once it has run its limit — 4 hours for FATPro,
     * 2 for a practitioner — with its time capped at that limit, and move on
     * to Step 6 (Interview Result).
     */
    private function stopInterviewIfOverLimit(Application $application, PctEntry $entry): bool
    {
        $limit            = $this->maxInterviewSeconds($application);
        $reference        = $entry->resumed_at ?? $entry->started_at;
        $elapsedWorking   = $entry->totalElapsedSeconds();
        $wallClockSeconds = $reference ? Carbon::now()->diffInSeconds($reference) : 0;

        if ($elapsedWorking < $limit && $wallClockSeconds < $limit) {
            return false;
        }

        $entry->elapsed_seconds = min($limit, max($entry->elapsed_seconds, $elapsedWorking));
        $entry->completed_at    = Carbon::now();
        $entry->is_active       = false;
        $entry->save();

        if (! $application->pctEntries()->where('step_number', 6)->exists()) {
            $this->startStep($application, 6);
        }

        $application->unsetRelation('pctEntries');
        $application->unsetRelation('activePctEntry');

        return true;
    }

    /**
     * Bulk auto-stop Step 5 (Interview) for all applications where the interview has exceeded 4 hours.
     */
    public function autoStopAllExpiredInterviews(): void
    {
        $runningEntries = PctEntry::where('step_number', 5)
            ->where('is_active', true)
            ->whereNull('paused_at')
            ->whereNull('completed_at')
            ->with('application')
            ->get();

        foreach ($runningEntries as $entry) {
            if ($entry->application) {
                $this->stopInterviewIfOverLimit($entry->application, $entry);
            }
        }
    }

    /**
     * Bulk auto-resume Step 5 (Interview) for any applications that have reached their scheduled time,
     * and auto-stop any running interviews that have exceeded 4 hours.
     * Throttled via cache to run at most once every 2 minutes to avoid a DB hit on every page load.
     */
    public function autoResumeAllScheduledInterviews()
    {
        $cacheKey = 'pct_auto_resume_ran';

        // Skip if we already ran this within the last 2 minutes
        if (Cache::has($cacheKey)) {
            return;
        }

        // Mark as run for the next 2 minutes
        Cache::put($cacheKey, true, now()->addMinutes(2));

        // 1. Auto-stop any expired interviews (> 4 hours)
        $this->autoStopAllExpiredInterviews();

        // 2. Find all active PCT entries for Step 5 that are paused
        $pausedEntries = PctEntry::where('step_number', 5)
            ->where('is_active', true)
            ->whereNotNull('paused_at')
            ->whereNull('completed_at')
            ->with('application.interview')
            ->get();

        foreach ($pausedEntries as $entry) {
            $application = $entry->application;
            if ($application && $application->interview) {
                $date = $application->interview->interview_date;
                $dateStr = $date instanceof Carbon ? $date->format('Y-m-d') : $date;
                $scheduledTimeStr = $dateStr . ' ' . $application->interview->interview_time;
                try {
                    $scheduledTime = Carbon::parse($scheduledTimeStr);
                    if (now()->greaterThanOrEqualTo($scheduledTime)) {
                        $entry->resume();

                        if ($application) {
                            $application->unsetRelation('pctEntries');
                            $application->unsetRelation('activePctEntry');
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore parse errors
                }
            }
        }
    }
}
