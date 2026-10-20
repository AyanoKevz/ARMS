<?php

namespace App\Mail;

use App\Models\PostTrainingReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A declined Post Training Report was not resubmitted within its correction
 * window, so it has been voided and can no longer be submitted.
 *
 * Sent to the FATPro (what happened and that it is final) and to the Training
 * Evaluators (so a report leaving their queue is not a surprise).
 */
class PostTrainingVoidedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const FOR_FATPRO    = 'fatpro';
    public const FOR_EVALUATOR = 'evaluator';

    public PostTrainingReport $report;
    public string $audience;

    public function __construct(PostTrainingReport $report, string $audience = self::FOR_FATPRO)
    {
        $this->report   = $report;
        $this->audience = $audience === self::FOR_EVALUATOR ? self::FOR_EVALUATOR : self::FOR_FATPRO;

        $this->report->loadMissing([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'ntcReport.trainingType',
            'ntcReport.trainingMode',
        ]);
    }

    public function forEvaluator(): bool
    {
        return $this->audience === self::FOR_EVALUATOR;
    }

    public function envelope(): Envelope
    {
        $ref = $this->report->reference_number;

        return new Envelope(
            subject: $this->forEvaluator()
                ? "Post Training Report Voided — {$ref} ({$this->report->accreditation?->user?->name})"
                : "Post Training Report Voided — {$ref}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ptr_voided');
    }
}
