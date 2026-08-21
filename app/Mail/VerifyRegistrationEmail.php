<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Deliberately NOT queued, unlike every other mailable in this directory.
 *
 * A registering user is blocked waiting for this link, so it must go out
 * immediately rather than on the next queue-worker tick (up to ~60s).
 *
 * It also has to send synchronously for the caller to work: RegistrationController
 * wraps the send in a try/catch that rolls back the pending registration and
 * returns an error when the mail server is unreachable. Queueing would make that
 * catch unreachable — the user would be told to check an inbox that never
 * receives anything.
 */
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
