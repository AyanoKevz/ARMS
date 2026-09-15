<?php

namespace App\Mail;

use App\Models\NtcReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * The post training report chase, in three stages:
 *
 *   STAGE_DUE      — the training has concluded, the clock has started
 *   STAGE_COUNTDOWN— sent each day of the window, with working days remaining
 *   STAGE_OVERDUE  — the deadline passed with nothing accepted
 *
 * The window itself is set by the training type (EFA 1, OFA 2, SFA 4 working
 * days), so the countdown wording is driven off the NTC rather than hardcoded.
 */
class PostTrainingReportDueEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const STAGE_DUE       = 'due';
    public const STAGE_COUNTDOWN = 'countdown';
    public const STAGE_OVERDUE   = 'overdue';

    public NtcReport $ntcReport;
    public string $stage;

    public function __construct(NtcReport $ntcReport, string $stage = self::STAGE_DUE)
    {
        $this->ntcReport = $ntcReport;
        $this->stage = in_array($stage, [self::STAGE_DUE, self::STAGE_COUNTDOWN, self::STAGE_OVERDUE], true)
            ? $stage
            : self::STAGE_DUE;

        $this->ntcReport->loadMissing([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
        ]);
    }

    public function isOverdue(): bool
    {
        return $this->stage === self::STAGE_OVERDUE;
    }

    public function envelope(): Envelope
    {
        $ref = $this->ntcReport->reference_number;

        if ($this->stage === self::STAGE_OVERDUE) {
            return new Envelope(subject: "Overdue: Post Training Report — {$ref}");
        }

        if ($this->stage === self::STAGE_COUNTDOWN) {
            $left = $this->ntcReport->postTrainingWorkingDaysRemaining();

            $subject = $left === 0
                ? "Final Day: Post Training Report Due Today — {$ref}"
                : "Reminder: {$left} working " . Str::plural('day', $left) . " left to submit your Post Training Report — {$ref}";

            return new Envelope(subject: $subject);
        }

        return new Envelope(subject: "Action Required: Post Training Report Due — {$ref}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ptr_due_reminder',
        );
    }
}
