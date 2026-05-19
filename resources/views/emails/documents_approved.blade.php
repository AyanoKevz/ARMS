@extends('emails.layout')

@section('title', 'Documents Approved — ARMS')

@section('css')
        .icon-circle.passed {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        }
@endsection

@section('content')
    <div class="icon-circle passed">📄</div>
    <h2>Documents Approved</h2>
    <p>
        Dear <strong>{{ $application->user->name ?? $application->user->email }}</strong>,
    </p>
    <p>
        We are pleased to inform you that all the documents you submitted for your 
        <strong>{{ ucfirst($application->application_type) }}</strong> application have been reviewed and <strong>approved</strong>.
    </p>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>
        <p class="label">Application Status</p>
        <p class="value-status">📅 Scheduled for Interview</p>
    </div>

    <p class="info-box blue">
        <strong>Next Step:</strong> Your application is now ready for the interview stage. 
        The administration will set a schedule for your interview, and you will receive another email 
        notification with the exact date, time, and venue/mode of the interview once it is finalized.
    </p>

    <p style="margin-top: 20px;">
        You can track the progress of your application anytime through your ARMS portal.
    </p>

    <div class="btn-wrap">
        <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-primary">
            Track My Application
        </a>
    </div>
@endsection
