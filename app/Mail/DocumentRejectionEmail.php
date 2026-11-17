<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;

class DocumentRejectionEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $application;
    public $rejectedDocuments;
    public $rejectedInstructors;
    public $rejectedCredentials;
    /** Instructors whose CV was rejected — the CV has its own status. */
    public $rejectedCvs;

    /**
     * Accreditation division applications: the last day to re-upload, after
     * which the application is archived (PractitionerArchiveService).
     */
    public ?\Carbon\Carbon $complianceDueDate = null;
    public int $reapplyMonths = 0;

    /**
     * Create a new message instance.
     *
     * @param Application $application
     * @param \Illuminate\Support\Collection $rejectedDocuments
     * @param \Illuminate\Support\Collection $rejectedInstructors
     * @param \Illuminate\Support\Collection $rejectedCredentials
     */
    public function __construct(Application $application, $rejectedDocuments, $rejectedInstructors = null, $rejectedCredentials = null, $rejectedCvs = null)
    {
        $this->application = $application;
        $this->application->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);
        
        $this->rejectedDocuments = $rejectedDocuments;
        if ($this->rejectedDocuments && $this->rejectedDocuments->isNotEmpty()) {
            $this->rejectedDocuments->loadMissing(['documentField.documentType']);
        }

        $this->rejectedInstructors = $rejectedInstructors ?? collect();
        $this->rejectedCvs = $rejectedCvs ?? collect();
        
        $this->rejectedCredentials = $rejectedCredentials ?? collect();
        if ($this->rejectedCredentials && $this->rejectedCredentials->isNotEmpty()) {
            $this->rejectedCredentials->loadMissing(['instructor']);
        }
    }

    /** State the re-upload deadline and what happens if it is missed. */
    public function withComplianceDeadline(\Carbon\Carbon $dueDate, int $reapplyMonths): static
    {
        $this->complianceDueDate = $dueDate;
        $this->reapplyMonths     = $reapplyMonths;

        return $this;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required: Document Revision Needed — ARMS',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->application->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);
        
        if ($this->rejectedDocuments && $this->rejectedDocuments->isNotEmpty()) {
            $this->rejectedDocuments->loadMissing(['documentField.documentType']);
        }
        
        if ($this->rejectedCredentials && $this->rejectedCredentials->isNotEmpty()) {
            $this->rejectedCredentials->loadMissing(['instructor']);
        }

        return new Content(
            view: 'emails.document_rejection',
        );
    }
}
