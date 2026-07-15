@extends('emails.layout')

@php
    $isAcknowledged = $rejectedDocuments->isEmpty();
    $wasReportChanges = $wasReportChanges ?? false;
@endphp

@section('title', $isAcknowledged ? ($wasReportChanges ? 'Report of Changes Acknowledged — ARMS' : 'Notice to Conduct (NTC) Acknowledged — ARMS') : ($wasReportChanges ? 'Action Required: Report of Changes Revision — ARMS' : 'Action Required: NTC Document Revision — ARMS'))

@section('css')
    @if($isAcknowledged)
        .icon-circle {
            background: linear-gradient(135deg, #e6fdf0, #c3f9d8);
            color: #2e7d32;
        }
    @else
        .icon-circle {
            background: linear-gradient(135deg, #fde8e8, #fcc5c5);
            color: #7a2222;
        }
        .doc-item-remark {
            color: #7a2222;
        }
    @endif
@endsection

@section('content')
    @if($isAcknowledged)
        <div class="icon-circle">✅</div>
        <h2>{{ $wasReportChanges ? 'Report of Changes Acknowledged' : 'Notice to Conduct Acknowledged' }}</h2>
        <p>
            We are pleased to inform you that your {{ $wasReportChanges ? 'Report of Changes' : 'Notice to Conduct (NTC)' }} submission has been fully reviewed and acknowledged.
        </p>
    @else
        <div class="icon-circle">⚠️</div>
        <h2>Action Required: {{ $wasReportChanges ? 'Report of Changes Revision' : 'NTC Document Revision' }}</h2>
        <p>
            We have reviewed your {{ $wasReportChanges ? 'Report of Changes' : 'Notice to Conduct' }} submission and found that some of the submitted
            documents require revision before your {{ $wasReportChanges ? 'Report of Changes' : 'NTC' }} can be acknowledged.
        </p>
    @endif

    <div class="tracking-card">
        <p class="label">{{ $wasReportChanges ? 'Reference Number' : 'NTC Reference Number' }}</p>
        <p class="value">{{ $ntcReport->reference_number }}</p>

        <p class="label">Training Type</p>
        <p class="value">{{ $ntcReport->trainingType->name ?? 'N/A' }}</p>

        <p class="label">Training Period</p>
        <p class="value">
            {{ $ntcReport->training_start_date?->format('M d, Y') }} – {{ $ntcReport->training_end_date?->format('M d, Y') }}
        </p>

        <p class="label">Status</p>
        @if($isAcknowledged)
            <p class="value-status" style="color: #2ecc71;">Acknowledged</p>
        @else
            <p class="value-status">Requires Re-submission</p>
        @endif
    </div>

    @if($isAcknowledged)
        <p>All submitted documents have been approved. You may now proceed with the conduct of this training program as scheduled.</p>
    @else
        <p>Please review the documents listed below and re-upload the corrected files through your {{ $wasReportChanges ? 'Report of Changes' : 'NTC' }} portal.</p>

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
    @endif

    <div class="btn-wrap">
        @if($isAcknowledged)
            <a href="{{ url('/applicant/ntc') }}" class="btn-primary">
                View My {{ $wasReportChanges ? 'Report of Changes' : 'NTC' }} Portal
            </a>
        @else
            <a href="{{ url('/applicant/ntc') }}" class="btn-primary">
                Log In &amp; Re-upload Documents
            </a>
        @endif
    </div>

    <p style="font-size:0.85rem; color:#888;">
        @if($isAcknowledged)
            This email serves as the official notification of your {{ $wasReportChanges ? 'Report of Changes' : 'Notice to Conduct' }} acknowledgement.
        @else
            If you have any questions, please contact our office directly.
        @endif
    </p>
@endsection
