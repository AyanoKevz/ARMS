@extends('emails.layout')

@section('title', 'Notification: Payment Requirements Submitted — ARMS')

@section('css')
        .icon-circle.admin {
            background: linear-gradient(135deg, #e0f2fe, #bae6fd);
        }
@endsection

@section('content')

    <div class="icon-circle admin">🔔</div>
    <h2>New Payment Submission</h2>
    <p>
        Hello HCD Verifier,
    </p>
    <p>
        An applicant has uploaded their payment requirements for verification. Please log into the portal to review their submission and upload the signed recommendation letter.
    </p>

    <div class="tracking-card">
        <p class="label">FATPro / Applicant Name</p>
        <p class="value">{{ $application->user->name }}</p>
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>
        <p class="label">Application Type</p>
        <p class="value-status">{{ ucfirst($application->application_type) }}</p>
    </div>

    <div class="btn-wrap">
        <a href="{{ url('/login') }}" class="btn-primary">
            Log in to Verifier Portal
        </a>
    </div>

    <p style="font-size:0.85rem; color:#888; margin-top: 16px;">
        This is an automated notification from ARMS. Please do not reply.
    </p>

@endsection
