@extends('emails.layout')

@php
    $isAccepted = $declinedDocuments->isEmpty();
    $ntc = $report->ntcReport;
@endphp

@section('title', $isAccepted ? 'Post Training Report Accepted — ARMS' : 'Action Required: Post Training Report Revision — ARMS')

@section('css')
    @if($isAccepted)
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
    @if($isAccepted)
        <div class="icon-circle">✅</div>
        <h2>Post Training Report Accepted</h2>
        <p>
            We are pleased to inform you that your Post Training Report has been fully reviewed and accepted.
        </p>
    @else
        <div class="icon-circle">⚠️</div>
        <h2>Action Required: Post Training Report Revision</h2>
        <p>
            We have reviewed your Post Training Report and found that some of the submitted documents
            were declined and require revision before the report can be accepted.
        </p>
    @endif

    <div class="tracking-card">
        <p class="label">Post Training Reference Number</p>
        <p class="value">{{ $report->reference_number }}</p>

        <p class="label">Related NTC</p>
        <p class="value">{{ $ntc->reference_number ?? 'N/A' }}</p>

        <p class="label">Training Type &amp; Mode</p>
        <p class="value">{{ $ntc->trainingType->name ?? 'N/A' }} ({{ $ntc->trainingMode->name ?? 'N/A' }})</p>

        <p class="label">Training Period</p>
        <p class="value">
            {{ $ntc && $ntc->training_start_date ? $ntc->training_start_date->format('M d, Y') : 'N/A' }}
            &ndash;
            {{ $ntc && $ntc->training_end_date ? $ntc->training_end_date->format('M d, Y') : 'N/A' }}
        </p>

        <p class="label">Status</p>
        @if($isAccepted)
            <p class="value-status" style="color: #2ecc71;">Accepted</p>
        @else
            <p class="value-status">Requires Re-submission</p>
        @endif
    </div>

    @if($isAccepted)
        <p>All submitted documents have been approved. No further action is required for this training.</p>
    @else
        <p>Please review the documents listed below and re-upload the corrected files through your Post Training Report portal.</p>

        <div class="doc-list">
            <p class="doc-list-title">📋 Documents Requiring Revision</p>
            @foreach($declinedDocuments as $doc)
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
        <a href="{{ url('/applicant/ntc') }}" class="btn-primary">
            {{ $isAccepted ? 'View My Post Training Reports' : 'Log In &amp; Re-upload Documents' }}
        </a>
    </div>

    <p style="font-size:0.85rem; color:#888;">
        @if($isAccepted)
            This email serves as the official notification that your Post Training Report has been accepted.
        @else
            If you have any questions, please contact our office directly.
        @endif
    </p>
@endsection
