<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\InstructorCredential;

class InstructorCredentialExpiryReminderEmail extends Mailable
{
    use Queueable, SerializesModels;

    public InstructorCredential $credential;
    public string $period;

    /**
     * Create a new message instance.
     */
    public function __construct(InstructorCredential $credential, string $period)
    {
        $this->credential = $credential;
        $this->credential->loadMissing(['instructor.user', 'instructor.application.user']);
        $this->period = $period;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Instructor Credential Expiring in ' . ucfirst($this->period) . ' — ARMS',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.instructor_credential_expiry_reminder',
        );
    }
}
