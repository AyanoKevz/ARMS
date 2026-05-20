@extends('emails.layout')

@section('title', 'Documents Resubmitted — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
        }
@endsection

@section('content')
    <div class="icon-circle">📂</div>
    <h2>Documents Resubmitted</h2>
    <p>
        An applicant has uploaded the requested documents for their application.
    </p>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>

        <p class="label">Resubmitted Items</p>
        <p class="value-status">{{ $resubmittedCount }} item(s) uploaded</p>
    </div>

    <div class="details-box">
        <h3>Applicant Details</h3>
        <p><strong>Applicant Name:</strong> {{ $application->user->name }}</p>
        <p><strong>Email:</strong> {{ $application->user->email }}</p>
        <p><strong>Application Type:</strong> {{ ucfirst($application->application_type) }}</p>
        <p><strong>Accreditation Type:</strong> {{ $application->accreditationType->name ?? 'N/A' }}</p>
        <p><strong>Resubmitted At:</strong> {{ now()->format('F d, Y h:i A') }}</p>
    </div>

    <p>The application is now back under review. Please log in to the admin portal to evaluate the updated files.</p>

    <div class="btn-wrap">
        <a href="{{ route('admin.hcd.applications.show', $application->id) }}" class="btn-primary">
            Review Resubmitted Documents
        </a>
    </div>
@endsection
