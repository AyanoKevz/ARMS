<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Accreditation division applicant: every document was approved, so they are
 * for interview and must choose an open slot on the tracking page.
 */
class InterviewSlotSelectionEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Application $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
        $this->application->loadMissing(['user.individualProfile', 'accreditationType']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documents Approved — Choose Your Interview Schedule (' . $this->application->tracking_number . ')',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.interview_slot_selection');
    }
}
