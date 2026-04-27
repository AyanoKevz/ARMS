<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Result — ARMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f0f2f5;
            color: #333;
        }

        .wrapper {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.10);
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(135deg, #1a2e5a 0%, #0d1f42 100%);
            padding: 30px 20px;
            text-align: center;
        }

        .logo-badge {
            display: inline-block;
            margin-bottom: 8px;
        }

        .logo-img {
            vertical-align: middle;
            max-height: 55px;
            width: auto;
            margin-right: 10px;
            border: none;
        }

        .logo-text {
            vertical-align: middle;
            font-size: 1.6rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 1.5px;
        }

        .header h1 {
            color: #D4AC4B;
            font-size: 1.15rem;
            font-weight: 500;
            margin: 0;
        }

        /* ── Body ── */
        .body {
            padding: 40px 48px;
            background-color: #f9f9f9;
        }

        .icon-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            margin: 0 auto 24px;
            font-size: 2rem;
            text-align: center;
            line-height: 72px;
            display: block;
        }

        .icon-circle.passed {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        }

        .icon-circle.failed {
            background: linear-gradient(135deg, #fde8e8, #fcc5c5);
        }

        .body h2 {
            font-size: 1.4rem;
            font-weight: 700;
            text-align: center;
            color: #1a2e5a;
            margin-bottom: 16px;
        }

        .body p {
            font-size: 0.95rem;
            line-height: 1.7;
            color: #555;
            text-align: center;
            margin-bottom: 12px;
        }

        /* ── Tracking Card ── */
        .tracking-card {
            background: linear-gradient(135deg, #1a2e5a, #0d1f42);
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .tracking-card p.label {
            color: rgba(255, 255, 255, 0.65);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .tracking-card p.value {
            color: #D4AC4B;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 15px;
        }

        .tracking-card p.value-status {
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 600;
        }

        /* ── Accreditation Details List ── */
        .doc-list {
            margin: 24px 0;
        }

        .doc-list-title {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1a2e5a;
            margin-bottom: 10px;
            text-align: left;
        }

        .doc-item {
            background: #fff;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 10px;
            text-align: left;
        }

        .doc-item.green {
            border: 1px solid #d0f0d0;
            border-left: 4px solid #27ae60;
        }

        .doc-item.red {
            border: 1px solid #f0d0d0;
            border-left: 4px solid #cc2222;
        }

        .doc-item-name {
            font-weight: 700;
            font-size: 0.93rem;
            color: #1a2e5a;
            margin-bottom: 4px;
        }

        .doc-item-value {
            font-size: 0.88rem;
            color: #27ae60;
            font-weight: 600;
        }

        /* ── Accent Box ── */
        .info-box {
            font-size: 0.85rem;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 16px 0;
            text-align: left;
        }

        .info-box.blue {
            color: #1a6fbd;
            background-color: #e6f2ff;
            border: 1px solid #b3d7ff;
        }

        .info-box.red {
            color: #922b21;
            background-color: #fdf2f2;
            border: 1px solid #f5c6c6;
        }

        /* ── Button ── */
        .btn-wrap {
            text-align: center;
            margin: 32px 0 28px;
        }

        .btn-track {
            display: inline-block;
            background: linear-gradient(135deg, #D4AC4B, #b8922e);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 10px;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 16px rgba(212, 172, 75, 0.35);
        }

        /* ── Footer ── */
        .footer {
            background: #f7f8fa;
            border-top: 1px solid #eee;
            padding: 24px 40px;
            text-align: center;
        }

        .footer p {
            font-size: 0.78rem;
            color: #aaa;
            line-height: 1.6;
        }

        .footer strong {
            color: #888;
        }
    </style>
</head>

<body>
    <div class="wrapper">

        {{-- ── Header ── --}}
        <div class="header">
            <div class="logo-badge">
                <img src="{{ $message->embed(public_path('images/oshc-logo.png')) }}" alt="OSHC Logo" class="logo-img">
                <span class="logo-text">OSHC-ARMS</span>
            </div>
            <h1>Accreditation Reporting and Monitoring System</h1>
        </div>

        {{-- ── Body ── --}}
        <div class="body">

            @if($result === 'passed')
            {{-- ════════════════ PASSED ════════════════ --}}

            <div class="icon-circle passed">🎉</div>
            <h2>Congratulations! Interview Passed</h2>
            <p>
                Dear <strong>{{ $application->user->name }}</strong>,
            </p>
            <p>
                We are delighted to inform you that you have <strong>successfully passed</strong> the interview
                for your accreditation application. Your accreditation has been officially approved.
            </p>

            <div class="tracking-card">
                <p class="label">Tracking Number</p>
                <p class="value">{{ $application->tracking_number }}</p>
                <p class="label">Application Status</p>
                <p class="value-status">✅ Approved – Accredited</p>
            </div>

            @if($accreditation)
            <div class="doc-list">
                <p class="doc-list-title">🏅 Your Accreditation Details</p>

                <div class="doc-item green">
                    <div class="doc-item-name">Accreditation Number</div>
                    <div class="doc-item-value">{{ $accreditation->accreditation_number }}</div>
                </div>

                <div class="doc-item green">
                    <div class="doc-item-name">Date of Accreditation</div>
                    <div class="doc-item-value">{{ $accreditation->date_of_accreditation->format('F d, Y') }}</div>
                </div>

                <div class="doc-item green">
                    <div class="doc-item-name">Valid Until</div>
                    <div class="doc-item-value">{{ $accreditation->validity_date->format('F d, Y') }}</div>
                </div>

                <div class="doc-item green">
                    <div class="doc-item-name">Accreditation Type</div>
                    <div class="doc-item-value">{{ $application->accreditationType->name ?? 'N/A' }}</div>
                </div>
            </div>

            <p class="info-box blue">
                <strong>Note:</strong> Please keep your accreditation number on record. You will need it for all 
                future transactions with OSHC. Your accreditation is valid for <strong>3 years</strong> from the date of issue.
            </p>
            @endif

            <div class="btn-wrap">
                <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-track">
                    View My Accreditation
                </a>
            </div>

            <p style="font-size:0.85rem; color:#888;">
                If you have any questions regarding your accreditation, please contact our office.
            </p>

            @else
            {{-- ════════════════ NOT PASSED ════════════════ --}}

            <div class="icon-circle failed">❌</div>
            <h2>Interview Result: Not Passed</h2>
            <p>
                Dear <strong>{{ $application->user->name }}</strong>,
            </p>
            <p>
                Thank you for your time and effort in undergoing the interview process. After careful evaluation,
                we regret to inform you that your application has <strong>not passed</strong> the interview stage
                at this time.
            </p>

            <div class="tracking-card">
                <p class="label">Tracking Number</p>
                <p class="value">{{ $application->tracking_number }}</p>
                <p class="label">Application Status</p>
                <p class="value-status">❌ Rejected – Not Passed</p>
            </div>

            <p>
                We understand this may be disappointing. We encourage you to review the accreditation requirements
                and consider re-applying in the future.
            </p>

            <p class="info-box red">
                <strong>Next Steps:</strong> You may re-apply for accreditation once you have addressed any 
                gaps identified during the evaluation process. Please contact our office for guidance on the 
                re-application procedure.
            </p>

            <p style="font-size:0.85rem; color:#888; margin-top: 16px;">
                If you believe this decision was made in error or have questions, please reach out to our office 
                as soon as possible.
            </p>

            @endif

        </div>

        {{-- ── Footer ── --}}
        <div class="footer">
            <p>
                &copy; {{ date('Y') }} <strong>ARMS — OSHC</strong><br>
                Occupational Safety and Health Center. All rights reserved.<br>
                This is an automated message. Please do not reply to this email.
            </p>
        </div>

    </div>
</body>

</html>
