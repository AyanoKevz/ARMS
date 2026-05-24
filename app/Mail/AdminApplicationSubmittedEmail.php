<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;

class AdminApplicationSubmittedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Application $application;

    /**
     * Create a new message instance.
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
        $this->application->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $type = ucfirst($this->application->application_type);
        return new Envelope(
            subject: "[Admin Notification] New {$type} Application Submitted — {$this->application->tracking_number}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_application_submitted',
        );
    }
}
