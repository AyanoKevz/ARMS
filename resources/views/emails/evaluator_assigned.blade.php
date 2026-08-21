@extends('emails.layout')

@section('title', 'Application Assigned To You — ARMS')

@section('css')
.icon-circle {
background: linear-gradient(135deg, #e0f2fe, #bae6fd);
}
@endsection

@section('content')
<div class="icon-circle">📋</div>
<h2>Application Assigned To You</h2>
<p>
  You have been assigned as the in-charge Evaluator for the following {{ $application->application_type }} application.
</p>

<div class="tracking-card">
  <p class="label">Tracking Number</p>
  <p class="value">{{ $application->tracking_number }}</p>
</div>

<div class="details-box">
  <h3>Applicant Details</h3>
  <p><strong>Applicant Name:</strong> {{ $application->user->name }}</p>
  <p><strong>Accreditation Type:</strong> {{ $application->accreditationType->name ?? 'N/A' }}</p>
  <p><strong>Application Type:</strong> {{ ucfirst($application->application_type) }}</p>
  <p><strong>Date Submitted:</strong> {{ $application->submitted_at ? \Carbon\Carbon::parse($application->submitted_at)->format('F d, Y h:i A') : 'N/A' }}</p>
  <p><strong>Date Assigned:</strong> {{ $application->updated_at ? \Carbon\Carbon::parse($application->updated_at)->format('F d, Y h:i A') : 'N/A' }}</p>
</div>

<p>Please log in to the admin portal to begin evaluating this application.</p>

<div class="btn-wrap">
  <a href="{{ route('admin.hcd.applications.show', $application->id) }}" class="btn-primary">
    View Application
  </a>
</div>
@endsection
