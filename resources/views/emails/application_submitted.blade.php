@extends('emails.layout')

@section('title', 'Application Submitted — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #d4f7e0, #a8efcc);
        }
@endsection

@section('content')
    <div class="icon-circle">✅</div>
    <h2>Application Submitted Successfully</h2>
    <p>
        Congratulations! Your application has been submitted successfully via the ARMS platform.
        Please keep this email as a copy of your tracking details.
    </p>

    <div class="tracking-card">
        <p class="label">Your Tracking Number</p>
        <p class="value">{{ $trackingNumber }}</p>

        <p class="label">Current Status</p>
        <p class="value-status">{{ $currentStatus }}</p>
    </div>

    <p>You can use your tracking number to check for updates on your application using our tracking page.</p>

    <div class="btn-wrap">
        <a href="{{ route('track') . '?tracking_number=' . $trackingNumber }}" class="btn-primary">
            Track Application Status
        </a>
    </div>
@endsection