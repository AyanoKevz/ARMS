<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;
use App\Models\Instructor;

class AdminDocumentsUploadedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Application $application;
    public int $resubmittedCount;
    public bool $isInstructorUpdate;
    public ?Instructor $instructor;

    /**
     * Create a new message instance.
     */
    public function __construct(Application $application, int $resubmittedCount, bool $isInstructorUpdate = false, ?Instructor $instructor = null)
    {
        $this->application = $application;
        $this->application->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);
        $this->resubmittedCount = $resubmittedCount;
        $this->isInstructorUpdate = $isInstructorUpdate;
        $this->instructor = $instructor;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        if ($this->isInstructorUpdate) {
            $instName = $this->instructor ? " — {$this->instructor->first_name} {$this->instructor->last_name}" : '';
            return new Envelope(
                subject: "[Admin Notification] Instructor Document Submission{$instName} ({$this->application->tracking_number})",
            );
        }

        return new Envelope(
            subject: "[Admin Notification] Requested Documents Resubmitted — {$this->application->tracking_number}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->application->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);
        return new Content(
            view: 'emails.admin_documents_uploaded',
        );
    }
}
