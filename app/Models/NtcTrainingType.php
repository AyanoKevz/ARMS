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
     * How many days of training this type requires.
     *
     * Calendar days, not working days: a training may run on a weekend, and its
     * days need not be consecutive. The number only says how many dates the NTC
     * form must collect — which dates they are is the FATPro's choice.
     */
    public function durationDays(): int
    {
        return NtcReport::durationDaysForCode($this->code);
    }

    /**
     * Contact hours this training runs for: eight per training day.
     */
    public function durationHours(): int
    {
        return $this->durationDays() * NtcReport::HOURS_PER_TRAINING_DAY;
    }

    /**
     * "1 day (8 hours)" / "4 days (32 hours)", for the type selector.
     *
     * Both figures, because they answer different questions: the hours are how
     * the curriculum prescribes the course, the days are how many dates the
     * FATPro will be asked to pick. A training day may itself run over more
     * than one date, so the day count is a minimum, not a total.
     */
    public function durationLabel(): string
    {
        $days = $this->durationDays();

        return $days . ' ' . ($days === 1 ? 'day' : 'days')
            . ' (' . $this->durationHours() . ' hours)';
    }
}
