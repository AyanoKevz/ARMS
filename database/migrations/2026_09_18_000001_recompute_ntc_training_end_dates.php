<?php

use App\Models\NtcReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bring existing Notices to Conduct onto the derived end date.
 *
 * The training type used to decide how long the FATPro had to file the post
 * training report, and the end date was typed in by hand. It now decides how
 * long the training itself runs (EFA 1, OFA 2, SFA 4 working days) and the end
 * date follows from it, while the reporting deadline is a flat five working
 * days for everyone.
 *
 * Rows written under the old rule can therefore hold an end date that no longer
 * matches their type. Left alone they would show one duration on screen and
 * another in the data, and their post training deadlines would be computed off
 * a date the form can no longer produce.
 */
return new class extends Migration
{
    public function up(): void
    {
        $codes = DB::table('ntc_training_types')->pluck('code', 'id');

        DB::table('ntc_reports')
            ->select('id', 'ntc_training_type_id', 'training_start_date')
            ->orderBy('id')
            ->chunk(200, function ($reports) use ($codes) {
                foreach ($reports as $report) {
                    if (!$report->training_start_date) {
                        continue;
                    }

                    $end = NtcReport::trainingEndDateFor(
                        \Carbon\Carbon::parse($report->training_start_date),
                        $codes[$report->ntc_training_type_id] ?? null
                    );

                    DB::table('ntc_reports')
                        ->where('id', $report->id)
                        ->update(['training_end_date' => $end->toDateString()]);
                }
            });
    }

    /**
     * The hand-entered end dates these replaced were free-form, so there is
     * nothing faithful to restore. Left deliberately empty rather than writing
     * back a guess.
     */
    public function down(): void
    {
        //
    }
};
