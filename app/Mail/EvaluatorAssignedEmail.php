<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;

class EvaluatorAssignedEmail extends Mailable implements ShouldQueue
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
        return new Envelope(
            subject: 'Application Assigned To You — ' . $this->application->tracking_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->application->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);

        return new Content(
            view: 'emails.evaluator_assigned',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
