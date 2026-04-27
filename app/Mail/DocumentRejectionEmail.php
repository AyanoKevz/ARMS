<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;

class DocumentRejectionEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $application;
    public $rejectedDocuments;

    /**
     * Create a new message instance.
     *
     * @param Application $application
     * @param \Illuminate\Support\Collection $rejectedDocuments
     */
    public function __construct(Application $application, $rejectedDocuments)
    {
        $this->application = $application;
        $this->rejectedDocuments = $rejectedDocuments;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required: Document Revision Needed — ARMS',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.document_rejection',
        );
    }
}
