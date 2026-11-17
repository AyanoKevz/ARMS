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
 * Accreditation division applicant: passed the panel interview. Their
 * recommendation is now prepared for the Office of the Executive Director;
 * the payment instructions follow once it is signed (PractitionerController::
 * uploadRecommendation).
 */
class PractitionerInterviewPassedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Application $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
        $this->application->loadMissing(['user', 'accreditationType']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Congratulations! You Passed the Panel Interview (' . $this->application->tracking_number . ')');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.practitioner_interview_passed');
    }
}
