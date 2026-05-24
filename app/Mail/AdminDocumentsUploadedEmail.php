<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;

class AdminDocumentsUploadedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Application $application;
    public int $resubmittedCount;

    /**
     * Create a new message instance.
     */
    public function __construct(Application $application, int $resubmittedCount)
    {
        $this->application = $application;
        $this->application->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);
        $this->resubmittedCount = $resubmittedCount;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Admin Notification] Requested Documents Resubmitted — {$this->application->tracking_number}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_documents_uploaded',
        );
    }
}
