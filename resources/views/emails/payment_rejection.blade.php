@extends('emails.layout')

@section('title', 'Action Required: Correct Your Payment Requirements — ARMS')

@section('css')
        .icon-circle.rejected {
            background: linear-gradient(135deg, #fde8e8, #fcc5c5);
        }
@endsection

@section('content')

    <div class="icon-circle rejected">⚠️</div>
    <h2>Action Required: Correct Payment Details</h2>
    <p>
        Dear <strong>{{ $application->user->name ?? $application->user->email }}</strong>,
    </p>
    <p>
        During the verification of your recommendation and payment details, our verifier identified some items that require your correction.
    </p>

    {{-- Highlighted 5-day deadline notice --}}
    <div style="background-color: #fff3cd; border: 2px solid #ffe69c; border-left: 6px solid #d97706; border-radius: 10px; padding: 16px 20px; margin: 18px 0; text-align: center;">
        <p style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px;">
            ⚠️ Important Resubmission Deadline
        </p>
        <p style="margin: 6px 0 0 0; font-size: 1.05rem; font-weight: 700; color: #b45309;">
            You have 5 days to resubmit your payment details.
        </p>
        <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: #78350f;">
            Please re-upload corrected proof of payment within 5 calendar days to avoid cancellation of your application.
        </p>
    </div>

    <div class="doc-list" style="margin-bottom: 20px;">
        <p class="doc-list-title">🔍 Payment Verification Feedback</p>

        @if($payment->proof_of_payment_status === 'rejected')
        <div class="doc-item red" style="margin-bottom: 12px; padding: 12px; border-left: 4px solid #dc3545; background-color: #fff8f8;">
            <div class="doc-item-name" style="font-weight: bold; color: #dc3545;">Proof of Payment — Rejected</div>
            <div class="doc-item-value" style="margin-top: 4px; font-style: italic; color: #555;">Remarks: {{ $payment->proof_of_payment_remarks ?? 'Please upload a clear copy of your payment receipt.' }}</div>
        </div>
        @endif
    </div>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>
        <p class="label">Application Status</p>
        <p class="value-status">⚠️ Revisions Required</p>
    </div>

    <p class="info-box red">
        <strong>What to do:</strong> Please re-upload the corrected files. You can do this by tracking your application on the public portal or by logging into your Applicant Portal.
    </p>

    <div class="btn-wrap">
        @if ($application->application_type === 'new')
            <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-primary" style="background-color: #dc3545;">
                Re-upload Payment Details
            </a>
        @else
            <a href="{{ route('applicant.dashboard') }}" class="btn-primary" style="background-color: #dc3545;">
                Re-upload Payment Details
            </a>
        @endif
    </div>

    <p style="font-size:0.85rem; color:#888;">
        If you have any questions, please contact our HCD office immediately.
    </p>

@endsection
