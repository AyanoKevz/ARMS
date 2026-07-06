<?php

namespace App\Mail;

use App\Models\NtcReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNtcReuploadedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public NtcReport $ntcReport;
    public array $reuploadedDocsInfo;

    /**
     * Create a new message instance.
     *
     * @param NtcReport $ntcReport
     * @param array $reuploadedDocsInfo List of document details that were reuploaded
     */
    public function __construct(NtcReport $ntcReport, array $reuploadedDocsInfo = [])
    {
        $this->ntcReport = $ntcReport;
        $this->reuploadedDocsInfo = $reuploadedDocsInfo;
        
        $this->ntcReport->loadMissing([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
        ]);
    }

    public function envelope(): Envelope
    {
        $fatproName = $this->ntcReport->accreditation->user->name ?? 'FATPro';
        $refNum = 'NTC-' . str_pad($this->ntcReport->id, 6, '0', STR_PAD_LEFT);
        return new Envelope(
            subject: "[Admin Notification] Rejected NTC Documents Re-uploaded — {$fatproName} ({$refNum})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_ntc_submitted',
            with: [
                'isReupload' => true,
                'reuploadedDocsInfo' => $this->reuploadedDocsInfo,
            ]
        );
    }
}
