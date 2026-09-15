<?php

namespace App\Mail;

use App\Models\PostTrainingReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminPostTrainingSubmittedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public PostTrainingReport $report;
    public bool $isReupload;
    public array $reuploadedDocsInfo;

    /**
     * @param array $reuploadedDocsInfo Document details that were re-uploaded
     */
    public function __construct(PostTrainingReport $report, bool $isReupload = false, array $reuploadedDocsInfo = [])
    {
        $this->report = $report;
        $this->isReupload = $isReupload;
        $this->reuploadedDocsInfo = $reuploadedDocsInfo;

        $this->report->loadMissing([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'ntcReport.trainingType',
            'ntcReport.trainingMode',
            'documents.documentType',
        ]);
    }

    public function envelope(): Envelope
    {
        $fatproName = $this->report->accreditation->user->name ?? 'FATPro';
        $ref = $this->report->reference_number;

        $subject = $this->isReupload
            ? "[Admin Notification] Declined Post Training Documents Re-uploaded — {$fatproName} ({$ref})"
            : "[Admin Notification] Post Training Report Submitted — {$fatproName} ({$ref})";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_ptr_submitted',
        );
    }
}
