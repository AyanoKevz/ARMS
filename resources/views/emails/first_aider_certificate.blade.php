@extends('emails.layout')

@php
    // $kind is one of FirstAiderCertificateEmail's KIND_* values.
    $fatproName = $accreditation->user->name ?? 'FATPro';
    $forEvaluator = $kind === 'submitted';
    $title = match ($kind) {
        'reminder'  => 'First-Aider Certificate Expiring Soon',
        'expired'   => 'First-Aider Certificate Expired',
        'submitted' => 'First-Aider Certificate Renewal for Review',
        'approved'  => 'First-Aider Certificate Renewal Approved',
        'rejected'  => 'First-Aider Certificate Renewal Rejected',
        default     => 'First-Aider Certificate',
    };
    $icon = match ($kind) {
        'approved' => '✅',
        'expired', 'rejected' => '⚠️',
        'submitted' => '📄',
        default => '⏰',
    };
@endphp

@section('title', $title . ' — ARMS')

@section('css')
    .icon-circle {
        background: linear-gradient(135deg, #fff7e6, #ffe6b3);
        color: #8a5a00;
    }
@endsection

@section('content')
    <div class="icon-circle">{{ $icon }}</div>
    <h2>{{ $title }}</h2>

    @switch($kind)
        @case('reminder')
            <p>
                The first-aider certificate on file for your accreditation expires in
                <strong>{{ $timeframe }}</strong>, on <strong>{{ $validityDate }}</strong>.
                Please upload your renewed certificate and its new validity date from your ARMS dashboard
                before then.
            </p>
            @break

        @case('expired')
            <p>
                The first-aider certificate on file for your accreditation expired on
                <strong>{{ $validityDate }}</strong>. Please upload your renewed certificate and its new
                validity date from your ARMS dashboard so the DOLE-OSHC evaluator can review it.
            </p>
            @break

        @case('submitted')
            <p>
                <strong>{{ $fatproName }}</strong> has uploaded a renewed first-aider certificate, valid until
                <strong>{{ $submission?->validity_date?->format('F d, Y') }}</strong>. Please review it on their
                application page.
            </p>
            @break

        @case('approved')
            <p>
                Your renewed first-aider certificate has been approved. It is now the certificate on file,
                valid until <strong>{{ $submission?->validity_date?->format('F d, Y') }}</strong>.
            </p>
            @break

        @case('rejected')
            <p>
                Your renewed first-aider certificate was not accepted. Please upload a corrected certificate
                from your ARMS dashboard. Your previous certificate remains on file in the meantime.
            </p>
            @if($submission?->remarks)
                <p><strong>Evaluator remarks:</strong> {{ $submission->remarks }}</p>
            @endif
            @break
    @endswitch

    <div class="tracking-card">
        <p class="label">FATPro</p>
        <p class="value">{{ $fatproName }}</p>

        <p class="label">Accreditation Number</p>
        <p class="value">{{ $accreditation->accreditation_number }}</p>

        <p class="label">Certificate Valid Until (on file)</p>
        <p class="value">{{ $validityDate ?? 'N/A' }}</p>

        @if($submission)
            <p class="label">Renewal Valid Until</p>
            <p class="value">{{ $submission->validity_date?->format('F d, Y') }}</p>
        @endif
    </div>

    <div class="btn-wrap">
        <a href="{{ $forEvaluator && $accreditation->application_id
                    ? url('/admin/hcd/applications/' . $accreditation->application_id)
                    : url('/applicant/dashboard') }}" class="btn-primary">
            {{ $forEvaluator ? 'Review the Renewal' : 'Open My Dashboard' }}
        </a>
    </div>
@endsection
