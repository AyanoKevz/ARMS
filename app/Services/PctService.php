<?php

namespace App\Services;

use App\Models\Application;
use App\Models\PctEntry;
use Carbon\Carbon;

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
     * Initialize PCT when an application is first moved to evaluation.
     * Auto-completes Steps 1 (Submission) and 2 (Receipt of Requirements)
     * since these are system-automatic steps.
     */
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
        $stepDef = self::STEPS[$stepNumber] ?? ['name' => "Step {$stepNumber}", 'target_days' => 1];
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
        $groups = $application->pctEntries()
            ->get()
            ->groupBy('step_number');

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
        // ── Clean up any duplicate entries for the same step ──
        $this->cleanupDuplicateEntries($application);

        if ($application->pctEntries()->exists()) {
            return;
        }

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

        // Complete Step 4, 5, 6
        $this->createCompletedStep($application, 4, $now);
        $this->createCompletedStep($application, 5, $now);
        $this->createCompletedStep($application, 6, $now);

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
        $stepDef = self::STEPS[$stepNumber] ?? ['name' => "Step {$stepNumber}", 'target_days' => 1];

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

    /**
     * Get Philippine public holidays for the given year range.
     * Returns an array of date strings in Y-m-d format.
     */
    public static function getHolidays(int $startYear, int $endYear): array
    {
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

        // ── Auto-Reconcile Step 7 state if signed recommendation is uploaded but payment is pending ──
        $payment = $application->payment;
        if ($payment && !$payment->proof_of_payment && $payment->signed_recommendation_letter) {
            $active = $application->pctEntries()->active()->where('step_number', 7)->first();
            if ($active && !$active->paused_at) {
                $active->pause();
            }
        }

        $entries = $application->pctEntries()->orderBy('step_number')->get();

        $totalElapsed = 0;
        $steps = [];

        foreach (self::STEPS as $num => $def) {
            $entry = $entries->firstWhere('step_number', $num);

            if ($entry) {
                $elapsed = $entry->totalElapsedSeconds();
                $totalElapsed += $elapsed;
                $elapsedDays = round($elapsed / 32400, 1);

                $steps[] = [
                    'number'      => $num,
                    'name'        => $def['name'],
                    'target_days' => $def['target_days'],
                    'status'      => $entry->stepStatus(),
                    'elapsed_days' => $elapsedDays,
                    'elapsed_seconds' => $elapsed,
                    'started_at'  => $entry->started_at,
                    'completed_at' => $entry->completed_at,
                    'paused_at'   => $entry->paused_at,
                    'is_overdue'  => $elapsedDays > $def['target_days'],
                    'percent'     => $def['target_days'] > 0 ? min(100, round(($elapsedDays / $def['target_days']) * 100)) : 0,
                ];
            } else {
                $steps[] = [
                    'number'      => $num,
                    'name'        => $def['name'],
                    'target_days' => $def['target_days'],
                    'status'      => 'pending',
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
            'total_target'   => self::TOTAL_TARGET_DAYS,
            'percent'        => self::TOTAL_TARGET_DAYS > 0 ? min(100, round(($totalDays / self::TOTAL_TARGET_DAYS) * 100)) : 0,
            'is_overdue'     => $totalDays > self::TOTAL_TARGET_DAYS,
            'has_entries'    => $entries->isNotEmpty(),
        ];
    }

    /**
     * Automatically resume Step 5 (Interview) if the scheduled date/time is reached/passed.
     */
    public function autoResumeInterviewIfScheduled(Application $application)
    {
        $active = $application->pctEntries()->where('step_number', 5)->where('is_active', true)->whereNotNull('paused_at')->first();
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
     * Bulk auto-resume Step 5 (Interview) for any applications that have reached their scheduled time.
     */
    public function autoResumeAllScheduledInterviews()
    {
        // Find all active PCT entries for Step 5 that are paused
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
                $dateStr = $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;
                $scheduledTimeStr = $dateStr . ' ' . $application->interview->interview_time;
                try {
                    $scheduledTime = \Carbon\Carbon::parse($scheduledTimeStr);
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
