<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstructorUpdateRequestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $instructor;

    /**
     * Create a new message instance.
     */
    public function __construct(\App\Models\Instructor $instructor)
    {
        $this->instructor = $instructor;
        $this->instructor->loadMissing(['user.organizationProfile', 'user.individualProfile', 'credentials']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required: Instructor Credentials Update Requested',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.instructor_update_request',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
