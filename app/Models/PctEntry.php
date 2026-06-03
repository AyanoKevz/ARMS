<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PctEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'step_name',
        'step_number',
        'target_days',
        'started_at',
        'paused_at',
        'resumed_at',
        'completed_at',
        'elapsed_seconds',
        'is_active',
    ];

    protected $casts = [
        'started_at'    => 'datetime',
        'paused_at'     => 'datetime',
        'resumed_at'    => 'datetime',
        'completed_at'  => 'datetime',
        'is_active'     => 'boolean',
        'step_number'   => 'integer',
        'target_days'   => 'integer',
        'elapsed_seconds' => 'integer',
    ];

    /* ── Relationships ─────────────────────────────────── */

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    /* ── Scopes ────────────────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForStep($query, int $stepNumber)
    {
        return $query->where('step_number', $stepNumber);
    }

    /* ── Helpers ───────────────────────────────────────── */

    /**
     * Pause the current step (admin is waiting for applicant).
     * Accumulates elapsed seconds up to now.
     */
    public function pause(): void
    {
        if (!$this->is_active || $this->paused_at) {
            return;
        }

        $reference = $this->resumed_at ?? $this->started_at;
        $now = Carbon::now();

        $this->elapsed_seconds += $reference ? \App\Services\PctService::calculateWorkingSeconds($reference, $now) : 0;
        $this->paused_at = $now;
        $this->is_active = true; // still "assigned" to this step, just paused
        $this->save();
    }

    /**
     * Resume the current step (applicant has resubmitted).
     */
    public function resume(): void
    {
        if (!$this->paused_at) {
            return;
        }

        $this->resumed_at = Carbon::now();
        $this->paused_at = null;
        $this->save();
    }

    /**
     * Complete the current step. Accumulates final elapsed seconds.
     */
    public function complete(): void
    {
        if ($this->completed_at) {
            return;
        }

        $now = Carbon::now();

        // If not paused, accumulate time from last resume (or start)
        if (!$this->paused_at) {
            $reference = $this->resumed_at ?? $this->started_at;
            $this->elapsed_seconds += $reference ? \App\Services\PctService::calculateWorkingSeconds($reference, $now) : 0;
        }

        $this->completed_at = $now;
        $this->is_active = false;
        $this->save();
    }

    /**
     * Get total elapsed time in seconds, including any currently running segment.
     */
    public function totalElapsedSeconds(): int
    {
        $total = $this->elapsed_seconds;

        // If active and not paused, add the current running segment
        if ($this->is_active && !$this->paused_at && !$this->completed_at) {
            $reference = $this->resumed_at ?? $this->started_at;
            if ($reference) {
                $total += \App\Services\PctService::calculateWorkingSeconds($reference, Carbon::now());
            }
        }

        return $total;
    }

    /**
     * Get elapsed time as fractional working days.
     * 1 working day = 9 hours = 32400 seconds.
     */
    public function elapsedWorkingDays(): float
    {
        return round($this->totalElapsedSeconds() / 32400, 1);
    }

    /**
     * Check if this step has exceeded its SLA target.
     */
    public function isOverdue(): bool
    {
        return $this->elapsedWorkingDays() > $this->target_days;
    }

    /**
     * Get percentage of target consumed (capped at 100 for display).
     */
    public function percentOfTarget(): float
    {
        if ($this->target_days <= 0) return 0;
        return min(100, round(($this->elapsedWorkingDays() / $this->target_days) * 100, 1));
    }

    /**
     * Get the status label for this PCT entry.
     */
    public function stepStatus(): string
    {
        if ($this->completed_at) return 'completed';
        if ($this->paused_at)    return 'paused';
        if ($this->is_active)    return 'active';
        return 'pending';
    }
}
