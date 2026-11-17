<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An interview time the Accreditation division has opened. Applicants pick
 * one from the tracking page; a booked slot holds that application.
 */
class InterviewSlot extends Model
{
    protected $fillable = [
        'slot_date',
        'start_time',
        'end_time',
        'mode',
        'venue',
        'application_id',
        'booked_at',
        'created_by',
    ];

    protected $casts = [
        'slot_date' => 'date',
        'booked_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Not booked and still ahead of us. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->upcoming()->whereNull('application_id');
    }

    /** Still ahead of us, booked or not. */
    public function scopeUpcoming(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->where(fn ($q) => $q
                ->whereDate('slot_date', '>', $now->toDateString())
                ->orWhere(fn ($t) => $t
                    ->whereDate('slot_date', $now->toDateString())
                    ->where('start_time', '>', $now->format('H:i:s'))));
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('slot_date')->orderBy('start_time');
    }

    public function isBooked(): bool
    {
        return $this->application_id !== null;
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->slot_date->format('Y-m-d') . ' ' . $this->start_time);
    }

    /** "9:00 AM – 10:30 AM" */
    public function timeLabel(): string
    {
        return Carbon::parse($this->start_time)->format('g:i A') . ' – ' . Carbon::parse($this->end_time)->format('g:i A');
    }

    public function modeLabel(): string
    {
        return $this->mode === 'online' ? 'Online' : 'Face-to-Face';
    }
}
