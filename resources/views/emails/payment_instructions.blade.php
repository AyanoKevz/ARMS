@extends('emails.layout')

@section('title', 'Action Required: Submit Recommendation and Payment — ARMS')

@section('css')
        .icon-circle.payment {
            background: linear-gradient(135deg, #fff8e1, #ffe082);
        }
@endsection

@section('content')

    <div class="icon-circle payment">🎉</div>
    <h2>Accreditation Approved & Payment Details</h2>
    <p>
        Dear <strong>{{ $application->user->name ?? $application->user->email }}</strong>,
    </p>
    <p>
        Congratulations on passing the evaluation and interview for your accreditation as a First Aid Training Provider!
    </p>
    <p>
        We are pleased to inform you that your application has been approved by the Office of the Executive Director.
    </p>
    <p>
        Here are the steps you need to follow for payment and document submission:
    </p>

    <div style="background: #fafafa; border: 1px solid #e5e5e5; border-radius: 8px; padding: 15px; margin-bottom: 20px; max-width: 500px;">
        <p style="margin-top: 0; margin-bottom: 10px; font-weight: bold; color: #1b5e20; font-size: 1.05rem;">1. Payment Details:</p>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.95rem; color: #333; margin-bottom: 10px;">
            <tr>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee;"><strong>Account Name</strong></td>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee; text-align: right;">Occupational Safety and Health Center</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee;"><strong>Account Number</strong></td>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee; text-align: right;">0711-0536-03</td>
            </tr>
            <tr style="font-weight: bold; color: #1b5e20; font-size: 1.05rem;">
                <td style="padding: 10px 0 0 0;">Amount Required</td>
                <td style="padding: 10px 0 0 0; text-align: right;">900.00 Pesos</td>
            </tr>
        </table>
    </div>

    <div style="background: #fafafa; border: 1px solid #e5e5e5; border-radius: 8px; padding: 15px; margin-bottom: 20px; max-width: 500px;">
        <p style="margin-top: 0; margin-bottom: 10px; font-weight: bold; color: #1b5e20; font-size: 1.05rem;">2. Mode of Payment:</p>
        <p style="margin: 0; font-size: 0.95rem; color: #333; line-height: 1.45;">
            Payments through GCash, Pay Maya, and other online banking methods are not advisable. Please use Landbank Mobile Banking or Landbank-Over-The-Counter only. You may also pay in Cash through our Finance and Admin Division.
        </p>
    </div>

    <div style="background: #fafafa; border: 1px solid #e5e5e5; border-radius: 8px; padding: 15px; margin-bottom: 20px; max-width: 500px;">
        <p style="margin-top: 0; margin-bottom: 10px; font-weight: bold; color: #1b5e20; font-size: 1.05rem;">3. Proof of Payment:</p>
        <ul style="margin: 0; padding-left: 20px; font-size: 0.95rem; color: #333; line-height: 1.45;">
            <li style="margin-bottom: 8px;">Ensure that the Branch of Account and Bank Teller’s machine validation are visibly printed on your receipt.</li>
            <li>After making your payment, provide a scanned copy of the deposit slip or a screenshot of the Proof of Payment, with the name of the payor, via email or portal upload.</li>
        </ul>
    </div>

    <p>
        We will update you as soon as they are ready for release.
    </p>

    <div class="tracking-card" style="margin-top: 25px;">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>
        <p class="label">Application Status</p>
        <p class="value-status">💳 Awaiting Payment</p>
    </div>

    <p class="info-box blue">
        <strong>How to Submit:</strong> You can submit these files by tracking your application on the public portal using your tracking number, or by logging into your Applicant Portal if you are applying for renewal or reinstatement.
    </p>

    <div class="btn-wrap">
        @if ($application->application_type === 'new')
            <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-primary">
                Submit Payment Details
            </a>
        @else
            <a href="{{ route('applicant.dashboard') }}" class="btn-primary">
                Submit Payment Details
            </a>
        @endif
    </div>

    <p>
        Thank you, and we look forward to your cooperation.
    </p>

@endsection
