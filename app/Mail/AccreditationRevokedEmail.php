<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Accreditation;

class AccreditationRevokedEmail extends Mailable
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
            subject: 'Accreditation Revoked — ARMS (' . $this->accreditation->accreditation_number . ')',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.accreditation_revoked',
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
