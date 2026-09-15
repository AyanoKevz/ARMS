@extends('emails.layout')

@php
    $ntc = $report->ntcReport;
@endphp

@section('title', $isReupload ? 'Declined Post Training Documents Re-uploaded — ARMS' : 'Post Training Report Submitted — ARMS')

@section('css')
    .icon-circle {
        background: linear-gradient(135deg, #fef9e7, #fdebd0);
    }
    .badge-ptr {
        display: inline-block;
        background: {{ $isReupload ? 'linear-gradient(135deg, #d32f2f, #b71c1c)' : 'linear-gradient(135deg, #0f766e, #115e59)' }};
        color: #fff;
        font-weight: 700;
        font-size: 0.75rem;
        padding: 4px 12px;
        border-radius: 20px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
@endsection

@section('content')
    <div class="icon-circle">{{ $isReupload ? '🔄' : '🏁' }}</div>
    <div style="text-align:center; margin-bottom: 10px;">
        <span class="badge-ptr">{{ $isReupload ? 'Post Training Re-upload' : 'Post Training Report' }}</span>
    </div>

    <h2>{{ $isReupload ? 'Declined Post Training Documents Re-uploaded' : 'Post Training Report Submitted' }}</h2>

    <p>
        @if($isReupload)
            An accredited FATPRO has re-uploaded the declined document(s) for their <strong>Post Training Report</strong> and is awaiting re-evaluation.
        @else
            An accredited FATPRO has submitted a <strong>Post Training Report</strong> for a concluded training and is awaiting evaluation.
        @endif
    </p>

    <div class="tracking-card">
        <p class="label">FATPRO / Organization</p>
        <p class="value" style="font-size: 1.15rem;">{{ $report->accreditation->user->name ?? 'N/A' }}</p>

        <p class="label">Accreditation Number</p>
        <p class="value-status">{{ $report->accreditation->accreditation_number ?? 'N/A' }}</p>

        <p class="label">Post Training Reference Number</p>
        <p class="value" style="font-size: 1.05rem; font-weight: bold; color: #ffffff;">{{ $report->reference_number }}</p>
    </div>

    <div class="details-box">
        <h3>Training Details</h3>
        <p><strong>Related NTC:</strong> {{ $ntc->reference_number ?? 'N/A' }}</p>
        <p><strong>Type of Training:</strong> {{ $ntc->trainingType->name ?? 'N/A' }}</p>
        <p><strong>Mode of Training:</strong> {{ $ntc->trainingMode->name ?? 'N/A' }}</p>
        @if($ntc && $ntc->venue)
            @if(optional($ntc->trainingMode)->code === 'BLENDED' || str_contains(strtolower($ntc->trainingMode->name ?? ''), 'blended'))
                <p><strong>Zoom Link / Meeting Link:</strong> <a href="{{ $ntc->venue }}" target="_blank">{{ $ntc->venue }}</a></p>
            @else
                <p><strong>Venue:</strong> {{ $ntc->venue }}</p>
            @endif
        @endif
        <p><strong>Training Period:</strong>
            {{ $ntc && $ntc->training_start_date ? $ntc->training_start_date->format('F d, Y') : 'N/A' }}
            &ndash;
            {{ $ntc && $ntc->training_end_date ? $ntc->training_end_date->format('F d, Y') : 'N/A' }}
        </p>
        <p><strong>Submission Deadline:</strong> {{ $report->due_date ? $report->due_date->format('F d, Y') : 'N/A' }}</p>
        @if(!$isReupload)
            <p><strong>Submitted At:</strong> {{ $report->submitted_at ? $report->submitted_at->format('F d, Y h:i A') : 'N/A' }}
                @if($report->wasSubmittedLate())
                    <span style="color:#d32f2f; font-weight:bold;">(Late Submission)</span>
                @endif
            </p>
        @endif
    </div>

    <div class="details-box">
        <h3>{{ $isReupload ? 'Re-uploaded Documents' : 'Attached Documents' }}</h3>
        @if($isReupload)
            @foreach($reuploadedDocsInfo as $info)
                <p><strong>{{ $info['type'] }}:</strong> {{ $info['filename'] }} <span style="font-size: 0.8rem; color: #27ae60;">(New File)</span></p>
            @endforeach
        @else
            @foreach($report->documents as $doc)
                <p><strong>{{ $doc->documentType->name ?? 'Document' }}:</strong> {{ $doc->original_filename }}</p>
            @endforeach
            @if($report->documents->isEmpty())
                <p style="color:#999;">No documents attached.</p>
            @endif
        @endif
    </div>

    <p>Please log in to the admin portal to evaluate this submission.</p>

    <div class="btn-wrap">
        <a href="{{ url('/admin/hcd/reports/post-training/' . $report->id) }}" class="btn-primary">
            View Submission
        </a>
    </div>
@endsection
