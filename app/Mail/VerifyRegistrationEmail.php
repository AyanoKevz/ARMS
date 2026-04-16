<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyRegistrationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verificationUrl;
    public string $applicantEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(string $verificationUrl, string $applicantEmail)
    {
        $this->verificationUrl  = $verificationUrl;
        $this->applicantEmail   = $applicantEmail;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email — ARMS Registration',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verify_registration',
        );
    }
}
