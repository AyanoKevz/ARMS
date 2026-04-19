<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;

class ApplicationSubmittedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $trackingNumber;
    public string $currentStatus;
    public string $applicantEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(string $trackingNumber, string $currentStatus, string $applicantEmail)
    {
        $this->trackingNumber = $trackingNumber;
        $this->currentStatus = $currentStatus;
        $this->applicantEmail = $applicantEmail;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Application Successfully Submitted — ARMS',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.application_submitted',
        );
    }
}
