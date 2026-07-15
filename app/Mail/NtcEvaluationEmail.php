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
    public bool $wasReportChanges;

    public function __construct(NtcReport $ntcReport, Collection $rejectedDocuments = null, bool $wasReportChanges = false)
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
        $this->wasReportChanges = $wasReportChanges;
    }

    public function envelope(): Envelope
    {
        $fatproName = $this->ntcReport->accreditation->user->name ?? 'FATPro';
        if ($this->rejectedDocuments->isEmpty()) {
            $prefix = $this->wasReportChanges ? 'Report of Changes Acknowledged' : 'Notice to Conduct (NTC) Acknowledged';
            return new Envelope(
                subject: "{$prefix} — {$this->ntcReport->reference_number}",
            );
        }
        $prefix = $this->wasReportChanges ? 'Report of Changes Document Revision Needed' : 'NTC Document Revision Needed';
        return new Envelope(
            subject: "Action Required: {$prefix} — {$fatproName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ntc_evaluation',
        );
    }
}
