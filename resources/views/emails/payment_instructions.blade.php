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
        To complete your accreditation, we kindly request you to settle the required fees. The total amount to be paid is <strong>PHP 900.00</strong>, with the breakdown as follows:
    </p>

    <div style="background: #fafafa; border: 1px solid #e5e5e5; border-radius: 8px; padding: 15px; margin-bottom: 20px; max-width: 450px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.95rem; color: #333;">
            <tr>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee;"><strong>Application Fee</strong></td>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee; text-align: right;">PHP 600.00</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee;"><strong>Accreditation Certification Fee</strong></td>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee; text-align: right;">PHP 300.00</td>
            </tr>
            <tr style="font-weight: bold; color: #1a4a8a; font-size: 1.05rem;">
                <td style="padding: 10px 0 0 0;">Total Fee Required</td>
                <td style="padding: 10px 0 0 0; text-align: right;">PHP 900.00</td>
            </tr>
        </table>
    </div>

    <p>
        Once payment is made, please upload the following payment requirement to verify your transaction:
    </p>

    <div class="doc-list" style="margin-bottom: 20px;">
        <p class="doc-list-title">📋 Payment Requirement</p>

        <div class="doc-item">
            <div class="doc-item-name">Proof of Payment</div>
            <div class="doc-item-value">Receipt / Deposit Slip / Transaction Screenshot (PDF or Image)</div>
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
