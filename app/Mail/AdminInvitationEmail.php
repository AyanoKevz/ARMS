<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Deliberately NOT queued — see VerifyRegistrationEmail for the same reasoning.
 *
 * ApplicationController::inviteAdmin() wraps the send in a try/catch that deletes
 * the pending_admins row and reports an error when the mail server is unreachable.
 * Queueing would make that catch unreachable, leaving an orphaned invitation row
 * while telling the inviter the email was sent.
 *
 * Inviting an admin is a rare action, not a hot path, so sending inline costs
 * nothing worth optimising.
 */
class AdminInvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $invitationUrl;
    public string $adminEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(string $invitationUrl, string $adminEmail)
    {
        $this->invitationUrl = $invitationUrl;
        $this->adminEmail    = $adminEmail;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Admin Invitation — ARMS',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_invitation',
        );
    }
}
