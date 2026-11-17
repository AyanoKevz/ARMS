@extends('emails.layout')

@section('title', 'Documents Approved — Choose Your Interview Schedule — ARMS')

@section('css')
    .icon-circle {
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
    }
@endsection

@section('content')
    <div class="icon-circle">📅</div>

    <h2>You Are for Interview</h2>

    <p>
        Dear <strong>{{ $application->user->name ?? $application->user->email }}</strong>,
    </p>

    <p>
        We are pleased to inform you that all the documents you submitted for your
        <strong>{{ $application->accreditationType->name ?? 'accreditation' }}</strong> application have been reviewed and <strong>approved</strong>.
        You may now proceed to the interview.
    </p>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>
        <p class="label">Application Status</p>
        <p class="value-status">📅 For Interview — Choose a Schedule</p>
    </div>

    <p class="info-box blue">
        <strong>Next Step:</strong> Open the tracking page and choose one of the open interview dates and times.
        Slots are first come, first served, so please choose as soon as you can. You will receive a confirmation
        email with the details of your interview once you have chosen.
    </p>

    <div class="btn-wrap">
        <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-primary">
            Choose My Interview Schedule
        </a>
    </div>
@endsection
