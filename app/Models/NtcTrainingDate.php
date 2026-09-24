<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One calendar day a training runs on.
 *
 * Training days are chosen individually — weekends count and the days need not
 * be consecutive — so the set cannot be reduced to a start date plus a length.
 * The NTC's training_start_date and training_end_date are the MIN and MAX of
 * these rows, rewritten by NtcReport::syncTrainingDates() whenever the set
 * changes, which lets every existing deadline keep reading those two columns.
 */
class NtcTrainingDate extends Model
{
    protected $fillable = [
        'ntc_report_id',
        'training_date',
        'day_no',
    ];

    protected $casts = [
        'training_date' => 'date',
    ];

    public function ntcReport()
    {
        return $this->belongsTo(NtcReport::class);
    }
}
