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
 *   STAGE_DUE     — the training has concluded, the clock has started
 *   STAGE_OVERDUE — the deadline passed with nothing accepted
 *
 * The window is five working days for every training type. The type governs
 * how long the training runs, not how long the FATPro has to report on it.
 */
class PostTrainingReportDueEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const STAGE_DUE     = 'due';
    public const STAGE_OVERDUE = 'overdue';

    public NtcReport $ntcReport;
    public string $stage;

    public function __construct(NtcReport $ntcReport, string $stage = self::STAGE_DUE)
    {
        $this->ntcReport = $ntcReport;
        $this->stage = in_array($stage, [self::STAGE_DUE, self::STAGE_OVERDUE], true)
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

        return new Envelope(subject: "Action Required: Post Training Report Due — {$ref}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ptr_due_reminder',
        );
    }
}
