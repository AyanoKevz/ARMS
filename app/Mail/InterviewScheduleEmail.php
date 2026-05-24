<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InterviewScheduleEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $application;
    public $interview;
    public $isUpdate;

    /**
     * Create a new message instance.
     */
    public function __construct(Application $application, Interview $interview, $isUpdate = false)
    {
        $this->application = $application;
        $this->application->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);
        $this->interview = $interview;
        $this->isUpdate = $isUpdate;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjectPrefix = $this->isUpdate ? 'Interview Schedule Updated' : 'Interview Schedule Confirmation';
        return new Envelope(
            subject: 'OSHC ARMS - ' . $subjectPrefix . ' (' . $this->application->tracking_number . ')',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.interview_scheduled',
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
