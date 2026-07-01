<?php

namespace App\Mail;

use App\Models\NtcDocument;
use App\Models\NtcReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class NtcDocumentRejectionEmail extends Mailable
{
    use Queueable, SerializesModels;

    public NtcReport $ntcReport;
    public Collection $rejectedDocuments;

    public function __construct(NtcReport $ntcReport, Collection $rejectedDocuments)
    {
        $this->ntcReport = $ntcReport;
        $this->ntcReport->loadMissing([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
        ]);

        $this->rejectedDocuments = $rejectedDocuments;
        $this->rejectedDocuments->loadMissing(['documentType']);
    }

    public function envelope(): Envelope
    {
        $fatproName = $this->ntcReport->accreditation->user->name ?? 'FATPro';
        return new Envelope(
            subject: "Action Required: NTC Document Revision Needed — {$fatproName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ntc_document_rejection',
        );
    }
}
