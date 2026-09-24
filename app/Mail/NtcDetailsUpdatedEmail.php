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
 * Tells a FATPro that an evaluator corrected their acknowledged NTC.
 *
 * The change is made on their behalf and without asking, so they have to hear
 * about it: the training dates may have moved, which moves the post training
 * deadline with them, and the instructors may no longer be who they named.
 *
 * Carries what the submission looked like BEFORE the edit as well as after,
 * because "your NTC was updated" is useless without saying what changed.
 */
class NtcDetailsUpdatedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public NtcReport $ntcReport;

    /** @var array<string, array{from: string, to: string}> */
    public array $changes;

    public ?string $note;

    /**
     * @param  array<string, array{from: string, to: string}>  $changes
     */
    public function __construct(NtcReport $ntcReport, array $changes, ?string $note = null)
    {
        $this->ntcReport = $ntcReport;
        $this->ntcReport->loadMissing([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
            'trainingDates',
            'instructors',
        ]);

        $this->changes = $changes;
        $this->note    = $note;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Notice to Conduct Was Updated — {$this->ntcReport->reference_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ntc_details_updated',
        );
    }
}
