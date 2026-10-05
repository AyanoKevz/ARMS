<?php

namespace App\Mail;

use App\Models\NtcReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the Training Evaluators a FATPro has called a training off.
 *
 * A Notice of Cancellation is not a request: it takes effect the moment it is
 * filed, and nobody is being asked to acknowledge or evaluate anything. The
 * message exists so a training the evaluators were expecting does not simply
 * vanish from their list unexplained — which is why the FATPro's reason is the
 * substance of it, not a footnote.
 *
 * Carries the training as it stood when it was cancelled, because by the time
 * this is read the row is already out of every working queue.
 */
class NtcCancelledEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public NtcReport $ntcReport;

    /** Who filed it — the FATPro's own name, not the account's. */
    public string $fatproName;

    public function __construct(NtcReport $ntcReport, string $fatproName)
    {
        $this->ntcReport  = $ntcReport;
        $this->fatproName = $fatproName;
    }

    public function envelope(): Envelope
    {
        // The shape every other admin notice uses, so this lands in the same
        // filter and reads the same way in a list of forty of them:
        // [Admin Notification] <what happened> — <who> (<reference>)
        $fatproName = $this->fatproName
            ?: ($this->ntcReport->accreditation->user->name ?? 'FATPro');

        $reference = $this->ntcReport->reference_number;

        return new Envelope(
            subject: "[Admin Notification] Training Cancelled — {$fatproName} ({$reference})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ntc_cancelled',
        );
    }
}
