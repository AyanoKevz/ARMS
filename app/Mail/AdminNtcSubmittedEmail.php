<?php

namespace App\Mail;

use App\Models\NtcReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNtcSubmittedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public NtcReport $ntcReport;

    public function __construct(NtcReport $ntcReport)
    {
        $this->ntcReport = $ntcReport;
        $this->ntcReport->loadMissing([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
            'documents.documentType',
        ]);
    }

    public function envelope(): Envelope
    {
        $fatproName = $this->ntcReport->accreditation->user->name ?? 'FATPro';
        return new Envelope(
            subject: "[Admin Notification] New Notice to Conduct Submitted — {$fatproName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_ntc_submitted',
        );
    }
}
