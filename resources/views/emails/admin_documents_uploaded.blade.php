@extends('emails.layout')

@php
    $isInstructor = $isInstructorUpdate ?? false;
@endphp

@section('title', ($isInstructor ? 'Instructor Document Submission' : 'Documents Resubmitted') . ' — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
        }
@endsection

@section('content')
    <div class="icon-circle">📂</div>
    <h2>{{ $isInstructor ? 'Instructor Document Submission' : 'Documents Resubmitted' }}</h2>
    <p>
        @if($isInstructor)
            An active FATPro has submitted updated instructor documents/credentials for review.
        @else
            An applicant has uploaded the requested documents for their application.
        @endif
    </p>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>

        <p class="label">{{ $isInstructor ? 'Submitted Items' : 'Resubmitted Items' }}</p>
        <p class="value-status">{{ $resubmittedCount }} item(s) uploaded</p>
    </div>

    <div class="details-box">
        <h3>{{ $isInstructor ? 'FATPro Details' : 'Applicant Details' }}</h3>
        <p><strong>{{ $isInstructor ? 'FATPro Name:' : 'Applicant Name:' }}</strong> {{ $application->user->name }}</p>
        <p><strong>Email:</strong> {{ $application->user->email }}</p>
        @if(!$isInstructor)
        <p><strong>Application Type:</strong> {{ ucfirst($application->application_type) }}</p>
        @endif
        <p><strong>Accreditation Type:</strong> {{ $application->accreditationType->name ?? 'N/A' }}</p>
        @if($isInstructor && isset($instructor))
        <p><strong>Instructor Name:</strong> {{ $instructor->first_name }} {{ $instructor->last_name }}</p>
        @endif
        <p><strong>Submitted At:</strong> {{ now()->format('F d, Y h:i A') }}</p>
    </div>

    <p>{{ $isInstructor ? 'The instructor credentials are now pending review. Please log in to the admin portal to evaluate the submitted instructor files.' : 'The application is now back under review. Please log in to the admin portal to evaluate the updated files.' }}</p>

    <div class="btn-wrap">
        <a href="{{ route('admin.hcd.applications.show', $application->id) }}" class="btn-primary">
            {{ $isInstructor ? 'Review Instructor Documents' : 'Review Resubmitted Documents' }}
        </a>
    </div>
@endsection
