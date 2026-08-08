<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\InstructorCredential;

class InstructorCredentialExpiredEmail extends Mailable
{
    use Queueable, SerializesModels;

    public InstructorCredential $credential;

    /**
     * Create a new message instance.
     */
    public function __construct(InstructorCredential $credential)
    {
        $this->credential = $credential;
        $this->credential->loadMissing(['instructor.user', 'instructor.application.user']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Instructor Credential Expired — ARMS',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.instructor_credential_expired',
        );
    }
}
