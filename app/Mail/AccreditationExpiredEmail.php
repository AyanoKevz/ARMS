<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Accreditation;

class AccreditationExpiredEmail extends Mailable
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
            subject: 'Accreditation Expired — ARMS',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.accreditation_expired',
        );
    }
}
