@extends('emails.layout')

@php
    $ntc         = $report->ntcReport;
    $fatproName  = $report->accreditation?->user?->name ?? 'the FATPro';
    $days        = \App\Models\PostTrainingReport::CORRECTION_WORKING_DAYS;
    $forEvaluator = $audience === \App\Mail\PostTrainingVoidedEmail::FOR_EVALUATOR;
@endphp

@section('title', 'Post Training Report Voided — ARMS')

@section('css')
    .icon-circle {
        background: linear-gradient(135deg, #ececec, #d4d4d4);
        color: #3f3f46;
    }
@endsection

@section('content')
    <div class="icon-circle">⛔</div>
    <h2>Post Training Report Voided</h2>

    @if($forEvaluator)
        <p>
            The Post Training Report of <strong>{{ $fatproName }}</strong> was declined and was not
            resubmitted within {{ $days }} working days. It has been voided automatically and has left
            the evaluation queue. No further action is needed from you.
        </p>
    @else
        <p>
            Your Post Training Report was declined and the required corrections were not resubmitted
            within <strong>{{ $days }} working days</strong>. It has therefore been
            <strong>voided</strong>, and a Post Training Report can no longer be submitted for this training.
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
            {{ $ntc?->training_start_date?->format('M d, Y') ?? 'N/A' }}
            &ndash;
            {{ $ntc?->training_end_date?->format('M d, Y') ?? 'N/A' }}
        </p>

        <p class="label">Declined On</p>
        <p class="value">{{ $report->declined_at?->format('F d, Y') ?? 'N/A' }}</p>

        <p class="label">Resubmission Deadline</p>
        <p class="value">{{ $report->correction_due_date?->format('F d, Y') ?? 'N/A' }}</p>

        <p class="label">Status</p>
        <p class="value-status" style="color: #3f3f46;">Voided</p>
    </div>

    @unless($forEvaluator)
        <p>If you believe this is a mistake, please contact the DOLE-OSHC Human Capital Development Division.</p>
    @endunless

    <div class="btn-wrap">
        <a href="{{ $forEvaluator ? url('/admin/hcd/reports/post-training') : url('/applicant/ntc') }}" class="btn-primary">
            {{ $forEvaluator ? 'View Post Training Reports' : 'Log In to ARMS' }}
        </a>
    </div>
@endsection
