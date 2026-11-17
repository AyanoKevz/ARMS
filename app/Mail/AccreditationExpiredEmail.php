<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Accreditation;

class AccreditationExpiredEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Accreditation $accreditation;

    /**
     * Create a new message instance.
     */
    public function __construct(Accreditation $accreditation)
    {
        $this->accreditation = $accreditation;
        $this->accreditation->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->forPractitioner() ? $this->typeTitle() . ' ' : '') . 'Accreditation Expired — ARMS',
        );
    }

    /**
     * Get the message content definition. OSH Practitioners (the
     * Accreditation division) get their own copy of the format.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->forPractitioner() ? 'emails.practitioner_accreditation_expired' : 'emails.accreditation_expired',
            with: ['typeTitle' => $this->typeTitle()],
        );
    }

    private function forPractitioner(): bool
    {
        return \App\Support\AdminAccess::isAccreditationDivisionType($this->accreditation->accreditation_type_id);
    }

    /** "OSH Practitioner" / "OSH Consultant" — read by the practitioner copy of the email. */
    public function typeTitle(): string
    {
        return \App\Support\AdminAccess::divisionTypeLabel($this->accreditation->accreditation_type_id);
    }
}
