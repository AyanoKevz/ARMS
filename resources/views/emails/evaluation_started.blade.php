@extends('emails.layout')

@section('title', 'Application Evaluation Started — ARMS')

@section('css')
.icon-circle {
background: linear-gradient(135deg, #e0f2fe, #bae6fd);
}
@endsection

@section('content')
<div class="icon-circle">🔍</div>
<h2>Evaluation Started</h2>
<p>
  Dear {{ $application->user->name }},
</p>
<p>
  Your {{ $application->application_type }} <strong>{{ $application->accreditationTitle() }}</strong> application with tracking number <strong>{{ $application->tracking_number }}</strong> is now <strong>Under Evaluation</strong>.
</p>

<div class="tracking-card">
  <p class="label">Tracking Number</p>
  <p class="value">{{ $application->tracking_number }}</p>
</div>

<div class="details-box">
  <h3>Application Details</h3>
  <p><strong>Accreditation Type:</strong> {{ $application->accreditationTitle() }}</p>
  <p><strong>Application Type:</strong> {{ ucfirst($application->application_type) }}</p>
  <p><strong>Date Submitted:</strong> {{ ($application->submitted_at ?? $application->created_at)?->format('F d, Y \a\t h:i A') }}</p>
  <p><strong>Evaluation Started:</strong> {{ $startedAt->format('F d, Y \a\t h:i A') }}</p>
</div>

<p>Our team is currently reviewing your submitted documents. We will notify you of any updates or if further information is required.</p>
@endsection