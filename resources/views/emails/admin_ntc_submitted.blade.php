@extends('emails.layout')

@section('title', isset($isReupload) && $isReupload ? 'Rejected NTC Documents Re-uploaded — ARMS' : 'New Notice to Conduct Submitted — ARMS')

@section('css')
    .icon-circle {
        background: linear-gradient(135deg, #fef9e7, #fdebd0);
    }
    .badge-ntc {
        display: inline-block;
        background: {{ isset($isReupload) && $isReupload ? 'linear-gradient(135deg, #d32f2f, #b71c1c)' : 'linear-gradient(135deg, #D4AC4B, #b8922e)' }};
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
    <div class="icon-circle">{{ isset($isReupload) && $isReupload ? '🔄' : '📋' }}</div>
    <div style="text-align:center; margin-bottom: 10px;">
        <span class="badge-ntc">{{ isset($isReupload) && $isReupload ? 'NTC Re-upload' : 'Notice to Conduct' }}</span>
    </div>
    <h2>{{ isset($isReupload) && $isReupload ? 'Rejected NTC Documents Re-uploaded' : 'New NTC Report Submitted' }}</h2>
    <p>
        @if(isset($isReupload) && $isReupload)
            An accredited FATPRO has re-uploaded the rejected document(s) for <strong>Notice to Conduct (NTC)</strong> report and is awaiting re-evaluation.
        @else
            An accredited FATPRO has submitted a <strong>Notice to Conduct (NTC)</strong> training report and is awaiting acknowledgement.
        @endif
    </p>

    <div class="tracking-card">
        <p class="label">FATPRO / Organization</p>
        <p class="value" style="font-size: 1.15rem;">{{ $ntcReport->accreditation->user->name ?? 'N/A' }}</p>

        <p class="label">Accreditation Number</p>
        <p class="value-status">{{ $ntcReport->accreditation->accreditation_number ?? 'N/A' }}</p>

        <p class="label">NTC Reference Number</p>
        <p class="value" style="font-size: 1.05rem; font-weight: bold; color: #ffffff;">NTC-{{ str_pad($ntcReport->id, 6, '0', STR_PAD_LEFT) }}</p>
    </div>

    <div class="details-box">
        <h3>Training Details</h3>
        <p><strong>Type of Training:</strong> {{ $ntcReport->trainingType->name ?? 'N/A' }}</p>
        <p><strong>Mode of Training:</strong> {{ $ntcReport->trainingMode->name ?? 'N/A' }}</p>
        <p><strong>Training Start Date:</strong> {{ $ntcReport->training_start_date ? $ntcReport->training_start_date->format('F d, Y') : 'N/A' }}</p>
        <p><strong>Training End Date:</strong> {{ $ntcReport->training_end_date ? $ntcReport->training_end_date->format('F d, Y') : 'N/A' }}</p>
        @if(!isset($isReupload) || !$isReupload)
            <p><strong>Submitted At:</strong> {{ $ntcReport->submitted_at ? $ntcReport->submitted_at->format('F d, Y h:i A') : 'N/A' }}</p>
        @endif
    </div>

    <div class="details-box">
        <h3>{{ isset($isReupload) && $isReupload ? 'Re-uploaded Documents' : 'Attached Documents' }}</h3>
        @if(isset($isReupload) && $isReupload && isset($reuploadedDocsInfo))
            @foreach($reuploadedDocsInfo as $info)
                <p><strong>{{ $info['type'] }}:</strong> {{ $info['filename'] }} <span style="font-size: 0.8rem; color: #27ae60;">(New File)</span></p>
            @endforeach
        @else
            @foreach($ntcReport->documents as $doc)
                <p><strong>{{ $doc->documentType->name ?? 'Document' }}:</strong> {{ $doc->original_filename }}</p>
            @endforeach
            @if($ntcReport->documents->isEmpty())
                <p style="color:#999;">No documents attached.</p>
            @endif
        @endif
    </div>

    <p>Please log in to the admin portal to evaluate or acknowledge this NTC submission.</p>

    <div class="btn-wrap">
        <a href="{{ url('/admin/hcd/reports/ntc/' . $ntcReport->id) }}" class="btn-primary">
            View NTC Submission
        </a>
    </div>
@endsection
