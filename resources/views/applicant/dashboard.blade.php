@extends('layouts.applicant')

@section('title', 'Applicant Dashboard')

@section('content')
@php
    $myAccreditation = \App\Models\Accreditation::where('user_id', auth()->id())
        ->whereIn('status', ['active', 'expired', 'revoked'])
        ->orderBy('created_at', 'desc')
        ->first();
@endphp

<div class="">
    <div class="page-title">
        <div class="title_left">
            <h3>Applicant Dashboard</h3>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="row">

        {{-- Accreditation Summary --}}
        @if($myAccreditation)
        <div class="col-md-12 col-sm-12">
            <div class="x_panel" style="border-left: 4px solid var(--portal-gold); border-top: none;">
                <div class="x_title border-0 mb-0 pb-0">
                    <h2 class="fw-bold" style="color: #2A3F54;"><i class="fas fa-award text-warning me-2"></i> Accreditation Summary</h2>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content mt-2">
                    <div class="row text-center text-md-start">
                        <div class="col-md-3 mb-2 mb-md-0 border-end">
                            <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Accreditation Number</p>
                            <p class="fw-bold fs-5 mb-0" style="color: #0b3d91;">{{ $myAccreditation->accreditation_number ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0 border-end">
                            <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Date Accredited</p>
                            <p class="fw-bold fs-5 mb-0" style="color: #2A3F54;">
                                {{ $myAccreditation->date_of_accreditation ? \Carbon\Carbon::parse($myAccreditation->date_of_accreditation)->format('F d, Y') : 'N/A' }}
                            </p>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0 border-end">
                            <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Validity Period</p>
                            <p class="fw-bold fs-5 mb-0" style="color: #2A3F54;">
                                {{ $myAccreditation->validity_date ? \Carbon\Carbon::parse($myAccreditation->validity_date)->format('F d, Y') : 'N/A' }}
                            </p>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Status</p>
                            <p class="mb-0 mt-1">
                                @if($myAccreditation->status === 'active')
                                    <span class="badge bg-success" style="font-size: 0.9rem; padding: 6px 12px;">Active</span>
                                @elseif($myAccreditation->status === 'expired')
                                    <span class="badge bg-warning text-dark" style="font-size: 0.9rem; padding: 6px 12px;">Expired</span>
                                @elseif($myAccreditation->status === 'revoked')
                                    <span class="badge bg-danger" style="font-size: 0.9rem; padding: 6px 12px;">Revoked</span>
                                @else
                                    <span class="badge bg-secondary" style="font-size: 0.9rem; padding: 6px 12px;">{{ ucfirst($myAccreditation->status) }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Overview card --}}
        <div class="col-md-12 col-sm-12">
            <div class="x_panel">
                <div class="x_title">
                    <h2>Overview</h2>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content">
                    <p>Welcome to the Applicant Portal. Start tracking your applications here.</p>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

