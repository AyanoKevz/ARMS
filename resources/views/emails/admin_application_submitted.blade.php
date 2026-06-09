@extends('emails.layout')

@section('title', 'New Application Submitted — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #e0f2fe, #bae6fd);
        }
@endsection

@section('content')
    <div class="icon-circle">📩</div>
    <h2>New Application Submitted</h2>
    <p>
        A new application has been submitted and is now awaiting evaluation.
    </p>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>

        <p class="label">Application Type</p>
        <p class="value-status">{{ ucfirst($application->application_type) }}</p>
    </div>

    <div class="details-box">
        <h3>Applicant Details</h3>
        <p><strong>Applicant Name:</strong> {{ $application->user->name }}</p>
        <p><strong>Email:</strong> {{ $application->user->email }}</p>
        <p><strong>Accreditation Type:</strong> {{ $application->accreditationType->name ?? 'N/A' }}</p>
        <p><strong>Submitted At:</strong> {{ $application->submitted_at ? \Carbon\Carbon::parse($application->submitted_at)->format('F d, Y h:i A') : 'N/A' }}</p>
    </div>

    <p>Please log in to the admin portal to evaluate this application.</p>

    <div class="btn-wrap">
        @if(in_array($application->application_type, ['renewal', 'reinstatement']))
            <a href="{{ route('admin.hcd.renewal.pending') }}" class="btn-primary">
                View Pending Renewals
            </a>
        @else
            <a href="{{ route('admin.hcd.applications.pending') }}" class="btn-primary">
                View Pending Applications
            </a>
        @endif
    </div>
@endsection
