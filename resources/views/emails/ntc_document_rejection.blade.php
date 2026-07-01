@extends('emails.layout')

@section('title', 'Action Required: NTC Document Revision — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #fde8e8, #fcc5c5);
        }

        .doc-item-remark {
            color: #7a2222;
        }
@endsection

@section('content')
    <div class="icon-circle">⚠️</div>
    <h2>Action Required: NTC Document Revision</h2>
    <p>
        We have reviewed your Notice to Conduct submission and found that some of the submitted
        documents require revision before your NTC can be acknowledged.
    </p>

    <div class="tracking-card">
        <p class="label">NTC Reference Number</p>
        <p class="value">{{ $ntcReport->reference_number }}</p>

        <p class="label">Training Type</p>
        <p class="value">{{ $ntcReport->trainingType->name ?? 'N/A' }}</p>

        <p class="label">Training Period</p>
        <p class="value">
            {{ $ntcReport->training_start_date?->format('M d, Y') }} – {{ $ntcReport->training_end_date?->format('M d, Y') }}
        </p>

        <p class="label">Status</p>
        <p class="value-status">Requires Re-submission</p>
    </div>

    <p>Please review the documents listed below and re-upload the corrected files through your NTC portal.</p>

    {{-- Rejected documents list --}}
    <div class="doc-list">
        <p class="doc-list-title">📋 Documents Requiring Revision</p>
        @foreach($rejectedDocuments as $doc)
        <div class="doc-item red">
            <div class="doc-item-name">{{ $doc->documentType->name ?? 'Document' }}</div>
            <div class="doc-item-remark">
                <span>Remarks:</span> {{ $doc->remarks ?: 'No specific remark provided. Please ensure the document is complete and legible.' }}
            </div>
        </div>
        @endforeach
    </div>

    <div class="btn-wrap">
        <a href="{{ url('/applicant/ntc') }}" class="btn-primary">
            Log In &amp; Re-upload Documents
        </a>
    </div>

    <p style="font-size:0.85rem; color:#888;">
        If you have any questions, please contact our office directly.
    </p>
@endsection
