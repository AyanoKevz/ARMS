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
    protected $description = 'Chase outstanding Post Training Reports: one due notice when the training concludes, and one overdue notice once the five-working-day deadline passes.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();

        $due     = $this->notifyDue($today);
        $overdue = $this->notifyOverdue($today);

        $this->info("Post training reminders sent: {$due} due, {$overdue} overdue.");

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
            ->where('training_end_date', '<', $today->toDateString())
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

                    if ($this->dispatchNotice($ntcReport, PostTrainingReportDueEmail::STAGE_DUE)) {
                        $ntcReport->update(['ptr_reminder_sent_at' => now()]);
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

            $message = $stage === PostTrainingReportDueEmail::STAGE_OVERDUE
                ? "The Post Training Report for {$ntcReport->reference_number} is overdue. The deadline was "
                    . "{$deadlineText}. Please submit it immediately."
                : "Your {$typeName} under {$ntcReport->reference_number} has concluded. You have "
                    . "{$daysAllowed} working " . Str::plural('day', $daysAllowed)
                    . " to file the Post Training Report — on or before {$deadlineText}.";

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
