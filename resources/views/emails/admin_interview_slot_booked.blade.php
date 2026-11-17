@extends('emails.layout')

{{-- Every Accreditation admin: an applicant booked an open interview slot on the tracking page. --}}
@section('title', 'Interview Schedule Booked — ARMS')

@section('css')
    .icon-circle {
        background: linear-gradient(135deg, #e0f2fe, #bae6fd);
    }
@endsection

@section('content')
    <div class="icon-circle">📅</div>
    <h2>Interview Schedule Booked</h2>
    <p>
        <strong>{{ $application->user->name }}</strong> has booked an interview schedule from the open interview
        slots for their <strong>{{ $application->accreditationTitle() }}</strong> application.
    </p>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>

        <p class="label">Interview Schedule</p>
        <p class="value-status">{{ $slot->slot_date->format('l, F j, Y') }} · {{ $slot->timeLabel() }}</p>
    </div>

    <div class="details-box">
        <h3>Interview Details</h3>
        <p><strong>Date:</strong> {{ $slot->slot_date->format('l, F j, Y') }}</p>
        <p><strong>Time:</strong> {{ $slot->timeLabel() }}</p>
        <p><strong>Mode:</strong> {{ $slot->modeLabel() }}</p>
        @if($slot->venue)
        <p><strong>{{ $slot->mode === 'online' ? 'Meeting Link:' : 'Venue:' }}</strong> {{ $slot->venue }}</p>
        @endif
        <p><strong>Booked On:</strong> {{ ($slot->booked_at ?? now())->format('F d, Y \a\t h:i A') }}</p>
    </div>

    <div class="details-box">
        <h3>Applicant Details</h3>
        <p><strong>Applicant Name:</strong> {{ $application->user->name }}</p>
        <p><strong>Email:</strong> {{ $application->user->email }}</p>
        <p><strong>Accreditation Type:</strong> {{ $application->accreditationTitle() }}</p>
        <p><strong>Application Type:</strong> {{ ucfirst($application->application_type) }}</p>
        <p><strong>Date Submitted:</strong> {{ ($application->submitted_at ?? $application->created_at)?->format('F d, Y \a\t h:i A') }}</p>
    </div>

    <p>The interview now appears under <strong>Schedule Interviews → Scheduled Interviews</strong> in the Accreditation portal.</p>

    <div class="btn-wrap">
        <a href="{{ $application->adminShowUrl() }}" class="btn-primary">View Application</a>
    </div>
@endsection
