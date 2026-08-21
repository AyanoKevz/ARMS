<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\ApplicationPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic queued notice for the simple application emails that were previously
 * sent with the closure form of Mail::send(). That form cannot be queued —
 * closures are not serializable — so every one of those calls blocked the
 * request on the SMTP handshake. This wraps the same views in a real mailable.
 *
 * Property names avoid $view and $subject, which Mailable already defines.
 */
class ApplicationNoticeEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Application $application,
        public string $viewName,
        public string $subjectLine,
        public ?ApplicationPayment $payment = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: $this->viewName,
            with: [
                'application' => $this->application,
                'payment'     => $this->payment,
            ],
        );
    }
}
