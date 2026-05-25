@extends('emails.layout')

@section('title', 'Action Required: Submit Recommendation and Payment — ARMS')

@section('css')
        .icon-circle.payment {
            background: linear-gradient(135deg, #fff8e1, #ffe082);
        }
@endsection

@section('content')

    <div class="icon-circle payment">💳</div>
    <h2>Action Required: Recommendation & Payment</h2>
    <p>
        Dear <strong>{{ $application->user->name ?? $application->user->email }}</strong>,
    </p>
    <p>
        We are pleased to inform you that you have <strong>successfully passed the interview stage</strong> of the accreditation process!
    </p>
    <p>
        To complete your accreditation, we kindly request you to upload the following payment requirements:
    </p>

    <div class="doc-list" style="margin-bottom: 20px;">
        <p class="doc-list-title">📋 Payment Requirements</p>

        <div class="doc-item">
            <div class="doc-item-name">1. Proof of Payment</div>
            <div class="doc-item-value">Receipt / Deposit Slip (PDF or Image)</div>
        </div>

        <div class="doc-item">
            <div class="doc-item-name">2. E-Signature</div>
            <div class="doc-item-value">Scanned signature (Image)</div>
        </div>

        <div class="doc-item">
            <div class="doc-item-name">3. ID Photo</div>
            <div class="doc-item-value">2x2 ID Photo (Image)</div>
        </div>
    </div>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>
        <p class="label">Application Status</p>
        <p class="value-status">💳 Awaiting Payment</p>
    </div>

    <p class="info-box blue">
        <strong>How to Submit:</strong> You can submit these files by tracking your application on the public portal using your tracking number, or by logging into your Applicant Portal if you are applying for renewal or reinstatement.
    </p>

    <div class="btn-wrap">
        <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-primary">
            Submit Payment Details
        </a>
    </div>

    <p style="font-size:0.85rem; color:#888;">
        If you have already submitted these details, please disregard this email. For any questions, please contact our HCD office.
    </p>

@endsection
