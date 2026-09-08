<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;

class InstructorRemovedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Application $application;
    public string $instructorName;
    public bool $wasApproved;

    /**
     * The instructor row is already gone by the time this is queued, so the name
     * and approval state are carried as plain values rather than a model.
     */
    public function __construct(Application $application, string $instructorName, bool $wasApproved = false)
    {
        $this->application = $application;
        $this->application->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);
        $this->instructorName = $instructorName;
        $this->wasApproved = $wasApproved;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Admin Notification] Instructor Removed — {$this->instructorName} ({$this->application->tracking_number})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->application->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);

        return new Content(
            view: 'emails.instructor_removed',
        );
    }
}
