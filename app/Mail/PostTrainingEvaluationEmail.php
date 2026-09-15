<?php

namespace App\Mail;

use App\Models\PostTrainingReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PostTrainingEvaluationEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public PostTrainingReport $report;
    public Collection $declinedDocuments;

    public function __construct(PostTrainingReport $report, Collection $declinedDocuments = null)
    {
        $this->report = $report;
        $this->report->loadMissing([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'ntcReport.trainingType',
            'ntcReport.trainingMode',
        ]);

        $this->declinedDocuments = $declinedDocuments ?? collect();
        $this->declinedDocuments->each(function ($doc) {
            if ($doc instanceof \Illuminate\Database\Eloquent\Model) {
                $doc->loadMissing(['documentType']);
            }
        });
    }

    public function envelope(): Envelope
    {
        $fatproName = $this->report->accreditation->user->name ?? 'FATPro';

        if ($this->declinedDocuments->isEmpty()) {
            return new Envelope(
                subject: "Post Training Report Accepted — {$this->report->reference_number}",
            );
        }

        return new Envelope(
            subject: "Action Required: Post Training Report Revision — {$fatproName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ptr_evaluation',
        );
    }
}
