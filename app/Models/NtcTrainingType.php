<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NtcTrainingType extends Model
{
    protected $fillable = ['name', 'code'];

    public function ntcReports()
    {
        return $this->hasMany(NtcReport::class);
    }

    /**
     * Working days this training runs. The NTC form reads it off each option to
     * fill in the read-only end date as the applicant chooses a type.
     */
    public function durationDays(): int
    {
        return NtcReport::durationDaysForCode($this->code);
    }

    /** "1 working day" / "4 working days", for labels and hints. */
    public function durationLabel(): string
    {
        $days = $this->durationDays();

        return $days . ' working ' . ($days === 1 ? 'day' : 'days');
    }
}
