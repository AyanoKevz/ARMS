<?php

namespace App\Mail;

use App\Models\NtcReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class NtcEvaluationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public NtcReport $ntcReport;
    public Collection $rejectedDocuments;

    public function __construct(NtcReport $ntcReport, Collection $rejectedDocuments = null)
    {
        $this->ntcReport = $ntcReport;
        $this->ntcReport->loadMissing([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
        ]);

        $this->rejectedDocuments = $rejectedDocuments ?? collect();
        $this->rejectedDocuments->loadMissing(['documentType']);
    }

    public function envelope(): Envelope
    {
        $fatproName = $this->ntcReport->accreditation->user->name ?? 'FATPro';
        if ($this->rejectedDocuments->isEmpty()) {
            return new Envelope(
                subject: "Notice to Conduct (NTC) Acknowledged — {$this->ntcReport->reference_number}",
            );
        }
        return new Envelope(
            subject: "Action Required: NTC Document Revision Needed — {$fatproName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ntc_evaluation',
        );
    }
}
