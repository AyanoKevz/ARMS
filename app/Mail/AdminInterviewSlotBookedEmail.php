<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\InterviewSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Every Accreditation admin: an applicant booked one of the open interview slots. */
class AdminInterviewSlotBookedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Application $application;
    public InterviewSlot $slot;

    public function __construct(Application $application, InterviewSlot $slot)
    {
        $this->application = $application;
        $this->application->loadMissing(['user.individualProfile', 'accreditationType']);
        $this->slot = $slot;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Admin Notification] Interview Schedule Booked — ' . $this->application->tracking_number,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin_interview_slot_booked');
    }
}
