<?php

namespace App\Console\Commands;

use App\Mail\PostTrainingReportDueEmail;
use App\Models\NtcReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PostTrainingReportReminderCheck extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'post-training:reminder-check';

    /**
     * The console command description.
     */
    protected $description = 'Chase outstanding Post Training Reports: a due notice when the training concludes, a daily countdown across the working-day window set by the training type, and an overdue notice once the deadline passes.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();

        $due       = $this->notifyDue($today);
        $countdown = $this->notifyCountdown($today);
        $overdue   = $this->notifyOverdue($today);

        $this->info("Post training reminders sent: {$due} due, {$countdown} countdown, {$overdue} overdue.");

        return self::SUCCESS;
    }

    /**
     * Acknowledged NTCs where the FATPro still owes us an action.
     *
     * "Not yet accepted" is the wrong test here: a report sitting with an
     * evaluator was filed on time, and chasing the FATPro over our own review
     * backlog would be telling them off for something they already did. What
     * genuinely remains on their side is either nothing filed at all, or a
     * document an evaluator declined that has not been re-uploaded.
     */
    private function outstanding(Carbon $today)
    {
        return NtcReport::query()
            ->where('status', 'acknowledged')
            ->whereDate('training_end_date', '<', $today)
            ->where(function ($q) {
                $q->whereDoesntHave('postTrainingReport')
                  ->orWhereHas(
                      'postTrainingReport.documents',
                      fn ($q2) => $q2->where('status', 'rejected')
                  );
            })
            ->with(['trainingType', 'trainingMode', 'accreditation.user', 'postTrainingReport']);
    }

    /**
     * First notice: the training is over, the report is now due.
     */
    private function notifyDue(Carbon $today): int
    {
        $sent = 0;

        $this->outstanding($today)
            ->whereNull('ptr_reminder_sent_at')
            ->chunkById(100, function ($reports) use (&$sent, $today) {
                foreach ($reports as $ntcReport) {
                    // A report already on file means they know; nothing to prompt.
                    if ($ntcReport->postTrainingReport) {
                        $ntcReport->update(['ptr_reminder_sent_at' => now()]);
                        continue;
                    }

                    // Already past the deadline the first time we see it — a
                    // backlog on first run, or cron having been down. Sending
                    // "your report is due" next to "your report is overdue" in
                    // the same minute reads as a system fault, so mark the
                    // gentle notice spent and let the overdue one speak alone.
                    $deadline = $ntcReport->postTrainingDeadlineDate();
                    if ($deadline && $today->greaterThan($deadline)) {
                        $ntcReport->update(['ptr_reminder_sent_at' => now()]);
                        continue;
                    }

                    // A one-working-day type (EFA) reaches its deadline on the
                    // same day this opening notice goes out. "You have 1 day"
                    // would be misleading there, so lead with the final-day
                    // wording instead.
                    $stage = ($deadline && $today->isSameDay($deadline))
                        ? PostTrainingReportDueEmail::STAGE_COUNTDOWN
                        : PostTrainingReportDueEmail::STAGE_DUE;

                    if ($this->dispatchNotice($ntcReport, $stage)) {
                        $ntcReport->update([
                            'ptr_reminder_sent_at' => now(),
                            // Claims today, so the countdown pass below does not
                            // send a second email on the same morning.
                            'ptr_last_reminded_on' => $today,
                        ]);
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * Daily countdown across the window the training type allows — the stretch
     * that used to be silent. A Standard First Aid report has four working days
     * to run down; hearing nothing until the overdue notice is how a deadline
     * gets missed.
     */
    private function notifyCountdown(Carbon $today): int
    {
        $sent = 0;

        $this->outstanding($today)
            // Only after the opening "it is due" notice has gone out.
            ->whereNotNull('ptr_reminder_sent_at')
            ->where(function ($q) use ($today) {
                $q->whereNull('ptr_last_reminded_on')
                  ->orWhereDate('ptr_last_reminded_on', '<', $today);
            })
            ->chunkById(100, function ($reports) use (&$sent, $today) {
                foreach ($reports as $ntcReport) {
                    // Nothing to chase once something has been filed; the
                    // evaluation emails take over from here.
                    if ($ntcReport->postTrainingReport) {
                        continue;
                    }

                    $deadline = $ntcReport->postTrainingDeadlineDate();

                    // Past the deadline is the overdue notice's job, not this one.
                    if (!$deadline || $today->greaterThan($deadline)) {
                        continue;
                    }

                    if ($this->dispatchNotice($ntcReport, PostTrainingReportDueEmail::STAGE_COUNTDOWN)) {
                        $ntcReport->update(['ptr_last_reminded_on' => $today]);
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * Final notice: the deadline has passed with nothing accepted.
     */
    private function notifyOverdue(Carbon $today): int
    {
        $sent = 0;

        $this->outstanding($today)
            ->whereNull('ptr_overdue_notified_at')
            ->chunkById(100, function ($reports) use (&$sent, $today) {
                foreach ($reports as $ntcReport) {
                    $deadline = $ntcReport->postTrainingDeadlineDate();
                    if (!$deadline || $today->lessThanOrEqualTo($deadline)) {
                        continue;
                    }

                    if ($this->dispatchNotice($ntcReport, PostTrainingReportDueEmail::STAGE_OVERDUE)) {
                        $ntcReport->update(['ptr_overdue_notified_at' => now()]);
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * Email the FATPro and drop an in-app notification. Returns false only when
     * there is nobody to notify, so a transient mail failure still marks the
     * notice as handled rather than re-sending it every day.
     */
    private function dispatchNotice(NtcReport $ntcReport, string $stage): bool
    {
        $user = $ntcReport->accreditation->user ?? null;
        if (!$user) {
            return false;
        }

        $deadline = $ntcReport->postTrainingDeadlineDate();

        try {
            if ($user->email) {
                Mail::to($user->email)->send(new PostTrainingReportDueEmail($ntcReport, $stage));
            }
        } catch (\Exception $e) {
            Log::warning("Post training {$ntcReport->reference_number} reminder email failed: " . $e->getMessage());
        }

        try {
            $deadlineText = $deadline?->format('F d, Y') ?? 'N/A';
            $daysAllowed  = $ntcReport->postTrainingDaysAllowed();
            $typeName     = $ntcReport->trainingType->name ?? 'training';
            $workingLeft  = $ntcReport->postTrainingWorkingDaysRemaining();

            $message = match ($stage) {
                PostTrainingReportDueEmail::STAGE_OVERDUE =>
                    "The Post Training Report for {$ntcReport->reference_number} is overdue. The deadline was "
                        . "{$deadlineText}. Please submit it immediately.",

                PostTrainingReportDueEmail::STAGE_COUNTDOWN => $workingLeft === 0
                    ? "Today is the last day to submit the Post Training Report for {$ntcReport->reference_number}. "
                        . "Deadline: {$deadlineText}."
                    : "{$workingLeft} working " . Str::plural('day', $workingLeft)
                        . " left to submit the Post Training Report for {$ntcReport->reference_number}. "
                        . "Deadline: {$deadlineText}.",

                default =>
                    "Your {$typeName} under {$ntcReport->reference_number} has concluded. A {$typeName} allows "
                        . "{$daysAllowed} working " . Str::plural('day', $daysAllowed)
                        . ", so submit the Post Training Report on or before {$deadlineText}.",
            };

            $user->notifications()->create([
                'id'   => Str::uuid(),
                'type' => 'App\Notifications\PostTrainingReportDueNotification',
                'data' => [
                    'ntc_report_id'    => $ntcReport->id,
                    'reference_number' => $ntcReport->reference_number,
                    'message'          => $message,
                    'link'             => '/applicant/ntc',
                ],
                'read_at' => null,
            ]);
        } catch (\Exception $e) {
            Log::warning("Post training {$ntcReport->reference_number} in-app notification failed: " . $e->getMessage());
        }

        return true;
    }
}
