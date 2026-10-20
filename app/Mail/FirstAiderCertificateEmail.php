<?php

namespace App\Mail;

use App\Models\Accreditation;
use App\Models\FirstAiderCertificateSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Every e-mail about a FATPro's first-aider certificate:
 *
 *   KIND_REMINDER  — to the FATPro, 3/2/1 months before it expires ($timeframe)
 *   KIND_EXPIRED   — to the FATPro, once its validity date has passed
 *   KIND_SUBMITTED — to the assigned evaluator, a renewal is waiting
 *   KIND_APPROVED  — to the FATPro, the renewal is now the certificate on file
 *   KIND_REJECTED  — to the FATPro, the renewal was rejected (with remarks)
 */
class FirstAiderCertificateEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const KIND_REMINDER  = 'reminder';
    public const KIND_EXPIRED   = 'expired';
    public const KIND_SUBMITTED = 'submitted';
    public const KIND_APPROVED  = 'approved';
    public const KIND_REJECTED  = 'rejected';

    public Accreditation $accreditation;
    public string $kind;
    public ?FirstAiderCertificateSubmission $submission;
    public ?string $timeframe;
    public ?string $validityDate;

    public function __construct(
        Accreditation $accreditation,
        string $kind,
        ?FirstAiderCertificateSubmission $submission = null,
        ?string $timeframe = null
    ) {
        $this->accreditation = $accreditation->loadMissing(['user', 'application']);
        $this->kind          = $kind;
        $this->submission    = $submission;
        $this->timeframe     = $timeframe;
        // Captured now: by the time a queued mail is sent the date on file may
        // already have changed.
        $this->validityDate  = $accreditation->firstAiderCertificateValidity()?->format('F d, Y');
    }

    public function envelope(): Envelope
    {
        $name = $this->accreditation->user->name ?? 'FATPro';

        return new Envelope(subject: match ($this->kind) {
            self::KIND_REMINDER  => "Reminder: First-Aider Certificate Expires in {$this->timeframe}",
            self::KIND_EXPIRED   => 'Action Required: First-Aider Certificate Expired',
            self::KIND_SUBMITTED => "First-Aider Certificate Renewal for Review — {$name}",
            self::KIND_APPROVED  => 'First-Aider Certificate Renewal Approved',
            self::KIND_REJECTED  => 'Action Required: First-Aider Certificate Renewal Rejected',
            default              => 'First-Aider Certificate — ARMS',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.first_aider_certificate');
    }
}
