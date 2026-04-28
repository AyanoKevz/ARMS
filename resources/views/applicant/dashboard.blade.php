@extends('layouts.applicant')

@section('title', 'Applicant Dashboard')

@section('content')
@php
    $myAccreditation = \App\Models\Accreditation::where('user_id', auth()->id())
        ->where('status', 'active')
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

        {{-- Certificate card (shown when accredited) --}}
        @if($myAccreditation)
        <div class="col-md-6 col-sm-12">
            <div class="x_panel" style="border-top: 3px solid #1a7a4a;">
                <div class="x_title">
                    <h2><i class="fas fa-certificate me-2 text-success"></i> Accreditation Certificate</h2>
                    <div class="clearfix"></div>
                </div>
                <div class="x_content">
                    <p class="mb-2">
                        <strong>Accreditation No:</strong> {{ $myAccreditation->accreditation_number }}<br>
                        <strong>Valid Until:</strong> {{ $myAccreditation->validity_date->format('F d, Y') }}
                    </p>
                    <a href="{{ route('applicant.certificate') }}"
                       target="_blank"
                       class="btn btn-success btn-sm fw-semibold"
                       style="border-radius:8px;">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Download Certificate PDF
                    </a>
                </div>
            </div>
        </div>
        @endif

        {{-- Overview card --}}
        <div class="col-md-{{ $myAccreditation ? '6' : '12' }} col-sm-12">
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

