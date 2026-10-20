<?php

namespace App\Console\Commands;

use App\Mail\PostTrainingVoidedEmail;
use App\Models\PostTrainingReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Voids every declined Post Training Report whose 10-working-day correction
 * window has closed without a resubmission.
 *
 * Runs daily. A report becomes voided the day AFTER its correction_due_date,
 * so the FATPro has the whole of the last day. Voided is final: the report can
 * no longer be corrected, evaluated or filed again. The FATPro and the Training
 * Evaluators are both e-mailed; the evaluators also get an in-app notice.
 */
class VoidLapsedPostTrainingReports extends Command
{
    protected $signature = 'post-training:void-lapsed';

    protected $description = 'Void declined Post Training Reports not resubmitted within '
        . PostTrainingReport::CORRECTION_WORKING_DAYS . ' working days, and notify the FATPro and Training Evaluators.';

    public function handle(): int
    {
        $today  = Carbon::today();
        $voided = 0;

        // Looked up once, not per report.
        $evaluators = User::whereHas('adminProfile.adminRole', fn ($q) => $q->where('name', 'Training Evaluator'))->get();

        PostTrainingReport::query()
            ->where('status', 'declined')
            ->whereNotNull('correction_due_date')
            ->whereDate('correction_due_date', '<', $today->toDateString())
            ->with(['accreditation.user', 'ntcReport'])
            ->chunkById(100, function ($reports) use (&$voided, $evaluators) {
                foreach ($reports as $report) {
                    // Conditional, so a correction filed while this ran is not
                    // overwritten: only a report still declined is voided.
                    $changed = PostTrainingReport::whereKey($report->id)
                        ->where('status', 'declined')
                        ->update(['status' => 'voided', 'voided_at' => now()]);

                    if (!$changed) {
                        continue;
                    }

                    $report->refresh();
                    $voided++;

                    $this->notify($report, $evaluators);
                }
            });

        $this->info("Voided {$voided} Post Training " . Str::plural('Report', $voided) . '.');

        return self::SUCCESS;
    }

    /**
     * Tell the FATPro and the Training Evaluators. A failed e-mail never undoes
     * the void — it is logged, and the report still shows as voided.
     */
    private function notify(PostTrainingReport $report, $evaluators): void
    {
        try {
            $fatproEmail = $report->accreditation?->user?->email;

            if ($fatproEmail) {
                Mail::to($fatproEmail)->send(new PostTrainingVoidedEmail($report, PostTrainingVoidedEmail::FOR_FATPRO));
            }

            if ($evaluators->isNotEmpty()) {
                Mail::to($evaluators->pluck('email')->all())
                    ->send(new PostTrainingVoidedEmail($report, PostTrainingVoidedEmail::FOR_EVALUATOR));
            }

            $fatproName = $report->accreditation?->user?->name ?? 'a FATPro';

            foreach ($evaluators as $evaluator) {
                $evaluator->notifications()->create([
                    'id'   => Str::uuid(),
                    'type' => 'App\Notifications\PostTrainingReportVoidedNotification',
                    'data' => [
                        'post_training_report_id' => $report->id,
                        'reference_number'        => $report->reference_number,
                        'message'                 => "Post Training Report {$report->reference_number} of {$fatproName} was voided: not resubmitted within "
                            . PostTrainingReport::CORRECTION_WORKING_DAYS . ' working days.',
                        'link'                    => "/admin/hcd/reports/post-training/{$report->id}",
                    ],
                    'read_at' => null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Post Training Report {$report->reference_number} voided, but its notice failed: " . $e->getMessage());
        }
    }
}
