<?php

namespace App\Mail;

use App\Models\Application;
use App\Services\PractitionerArchiveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Accreditation division applicant: the application was archived — the
 * rejected documents were not re-uploaded in time, or the panel interview
 * was not passed — and when they may apply again.
 */
class PractitionerApplicationArchivedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Application $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
        $this->application->loadMissing(['user', 'accreditationType']);
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->application->archive_reason) {
            PractitionerArchiveService::INTERVIEW_FAILED       => 'Interview Result: Did Not Pass',
            PractitionerArchiveService::PAYMENT_NON_COMPLIANCE => 'Application Archived — Payment Not Re-uploaded',
            default                                            => 'Application Archived — Documents Not Re-uploaded',
        };

        return new Envelope(subject: $subject . ' (' . $this->application->tracking_number . ')');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.practitioner_application_archived', with: [
            'interviewFailed' => $this->application->archive_reason === PractitionerArchiveService::INTERVIEW_FAILED,
            'paymentLapsed'   => $this->application->archive_reason === PractitionerArchiveService::PAYMENT_NON_COMPLIANCE,
            'months'          => PractitionerArchiveService::REAPPLY_MONTHS[$this->application->archive_reason] ?? 1,
            'workingDays'     => PractitionerArchiveService::COMPLIANCE_WORKING_DAYS,
        ]);
    }
}
