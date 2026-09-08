@extends('emails.layout')

@section('title', 'Instructor Removed — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
        }
@endsection

@section('content')
    <div class="icon-circle">🗑️</div>
    <h2>Instructor Removed</h2>
    <p>A FATPro has removed an instructor from their roster. The instructor's records and uploaded files have been deleted.</p>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>

        <p class="label">Removed Instructor</p>
        <p class="value-status">{{ $instructorName }}</p>
    </div>

    <div class="details-box">
        <h3>FATPro Details</h3>
        <p><strong>FATPro Name:</strong> {{ $application->user->name }}</p>
        <p><strong>Email:</strong> {{ $application->user->email }}</p>
        <p><strong>Accreditation Type:</strong> {{ $application->accreditationType->name ?? 'N/A' }}</p>
        <p><strong>Previously Approved:</strong> {{ $wasApproved ? 'Yes — this instructor had already been approved' : 'No — the instructor was still pending review' }}</p>
        <p><strong>Removed At:</strong> {{ now()->format('F d, Y h:i A') }}</p>
    </div>

    <p>Please review the FATPro's remaining instructor roster to confirm it still meets the accreditation requirements.</p>

    <div class="btn-wrap">
        <a href="{{ route('admin.hcd.applications.show', $application->id) }}" class="btn-primary">
            View Application
        </a>
    </div>
@endsection
