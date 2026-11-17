<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Accreditation;

class AccreditationExpiryReminderEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Accreditation $accreditation;
    public string $period;

    /**
     * Create a new message instance.
     */
    public function __construct(Accreditation $accreditation, string $period)
    {
        $this->accreditation = $accreditation;
        $this->accreditation->loadMissing(['user.organizationProfile', 'user.individualProfile', 'accreditationType']);
        $this->period = $period;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->forPractitioner() ? $this->typeTitle() . ' ' : '') . 'Accreditation Expiring in ' . ucfirst($this->period) . ' — ARMS',
        );
    }

    /**
     * Get the message content definition. OSH Practitioners (the
     * Accreditation division) get their own copy of the format.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->forPractitioner() ? 'emails.practitioner_accreditation_expiry_reminder' : 'emails.accreditation_expiry_reminder',
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
