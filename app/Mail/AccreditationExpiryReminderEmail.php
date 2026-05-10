<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Accreditation;

class AccreditationExpiryReminderEmail extends Mailable
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
        $this->period = $period;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Accreditation Expiring in ' . ucfirst($this->period) . ' — ARMS',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.accreditation_expiry_reminder',
        );
    }
}
