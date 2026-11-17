@extends('layouts.applicant')

@section('title', 'Practitioner Dashboard')

{{--
    OSH Practitioner portal dashboard: the accreditation summary (FATPro's
    card) and the certificate / ID card release. Practitioners file no
    training reports, so nothing else is here.
--}}
@section('content')
@php
    $accreditation = \App\Models\Accreditation::where('user_id', auth()->id())
        ->whereIn('status', ['active', 'expired', 'revoked'])
        ->latest('id')
        ->first();
    $application = $accreditation?->application;

    $statusBadge = match ($accreditation?->status) {
        'active'  => ['bg-success', 'Active'],
        'expired' => ['bg-warning text-dark', 'Expired'],
        'revoked' => ['bg-danger', 'Revoked'],
        default   => ['bg-secondary', ucfirst((string) $accreditation?->status)],
    };
@endphp

<div class="page-title">
    <div class="title_left">
        <h3>Practitioner Dashboard</h3>
    </div>
</div>
<div class="clearfix"></div>

@if(session('error'))
    <div class="alert alert-warning alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($accreditation)
<div class="x_panel ap-summary">
    <div class="x_title border-0 mb-0 pb-0">
        <h2 class="fw-bold ap-title"><i class="fas fa-award text-warning me-2"></i> Accreditation Summary</h2>
        <div class="clearfix"></div>
    </div>
    <div class="x_content mt-2">
        <div class="row text-center text-md-start">
            <div class="col-md mb-2 mb-md-0 border-end">
                <p class="ap-label">Accreditation Number</p>
                <p class="ap-value is-number">{{ $accreditation->accreditation_number }}</p>
            </div>
            <div class="col-md mb-2 mb-md-0 border-end">
                <p class="ap-label">Accredited As</p>
                <p class="ap-value">{{ $application?->accreditationTitle() ?? 'OSH Practitioner' }}</p>
            </div>
            <div class="col-md mb-2 mb-md-0 border-end">
                <p class="ap-label">Date Accredited</p>
                <p class="ap-value">{{ $accreditation->date_of_accreditation?->format('F d, Y') ?? 'N/A' }}</p>
            </div>
            <div class="col-md mb-2 mb-md-0 border-end">
                <p class="ap-label">Validity Period</p>
                <p class="ap-value">{{ $accreditation->validity_date?->format('F d, Y') ?? 'N/A' }}</p>
            </div>
            <div class="col-md">
                <p class="ap-label">Status</p>
                <p class="mb-0 mt-1"><span class="badge ap-status {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span></p>
            </div>
        </div>
    </div>
</div>

<div class="x_panel">
    <div class="x_title">
        <h2><i class="fas fa-id-card me-2 ap-gold"></i> Certificate of Accreditation &amp; Identification Card</h2>
        <div class="clearfix"></div>
    </div>
    <div class="x_content">
        @if($accreditation->released_at)
            <div class="ap-release is-ready">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Ready for pickup</strong> since {{ $accreditation->released_at->format('F d, Y') }}.
                    Please claim your Certificate of Accreditation and Identification Card at the Accreditation Division
                    office during regular office hours, and bring a valid government-issued ID.
                </div>
            </div>
        @else
            <div class="ap-release">
                <i class="fas fa-hourglass-half"></i>
                <div>
                    Your Certificate of Accreditation and Identification Card are being prepared.
                    We will email you once they are ready for pickup.
                </div>
            </div>
        @endif

        @if($application)
            <p class="ap-tracking mb-0">
                Application tracking number: <strong>{{ $application->tracking_number }}</strong>
                — <a href="{{ route('track', ['tracking_number' => $application->tracking_number]) }}">view its history</a>
            </p>
        @endif
    </div>
</div>
@else
<div class="x_panel">
    <div class="x_content">
        <p class="mb-0 text-muted">No accreditation is on record for this account.</p>
    </div>
</div>
@endif
@endsection
