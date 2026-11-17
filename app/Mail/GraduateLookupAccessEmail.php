<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The Graduate Lookup access link.
 *
 * Deliberately NOT queued, like VerifyRegistrationEmail: the visitor is waiting
 * for this link, and GraduateLookupController removes the request and reports
 * an error when the mail server is unreachable — which only works if the send
 * happens here and now.
 */
class GraduateLookupAccessEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $accessUrl,
        public string $visitorName,
        public int $validHours,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Access Link — ARMS Graduate Lookup',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.graduate_lookup_access',
        );
    }
}
