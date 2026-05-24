<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;

class DocumentRejectionEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $application;
    public $rejectedDocuments;
    public $rejectedInstructors;
    public $rejectedCredentials;

    /**
     * Create a new message instance.
     *
     * @param Application $application
     * @param \Illuminate\Support\Collection $rejectedDocuments
     * @param \Illuminate\Support\Collection $rejectedInstructors
     * @param \Illuminate\Support\Collection $rejectedCredentials
     */
    public function __construct(Application $application, $rejectedDocuments, $rejectedInstructors = null, $rejectedCredentials = null)
    {
        $this->application = $application;
        $this->application->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);
        
        $this->rejectedDocuments = $rejectedDocuments;
        if ($this->rejectedDocuments && $this->rejectedDocuments->isNotEmpty()) {
            $this->rejectedDocuments->loadMissing(['documentField.documentType']);
        }

        $this->rejectedInstructors = $rejectedInstructors ?? collect();
        
        $this->rejectedCredentials = $rejectedCredentials ?? collect();
        if ($this->rejectedCredentials && $this->rejectedCredentials->isNotEmpty()) {
            $this->rejectedCredentials->loadMissing(['instructor']);
        }
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
        return new Content(
            view: 'emails.document_rejection',
        );
    }
}
