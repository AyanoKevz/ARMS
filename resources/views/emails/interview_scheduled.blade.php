<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Schedule Confirmation — ARMS</title>
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

        .body {
            padding: 40px 48px;
            background-color: #f9f9f9;
        }

        .body .icon-circle {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 50%;
            margin: 0 auto 24px;
            font-size: 2rem;
            text-align: center;
            line-height: 72px;
            display: block;
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

        /* Details list */
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
            border: 1px solid #d0f0d0;
            border-left: 4px solid #27ae60;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 10px;
            text-align: left;
        }

        .doc-item-name {
            font-weight: 700;
            font-size: 0.93rem;
            color: #1a2e5a;
            margin-bottom: 4px;
        }

        .doc-item-remark {
            font-size: 0.85rem;
            color: #27ae60;
        }

        .doc-item-remark span {
            font-weight: 600;
        }

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
        {{-- Header --}}
        <div class="header">
            <div class="logo-badge">
                <img src="{{ $message->embed(public_path('images/oshc-logo.png')) }}" alt="OSHC Logo" class="logo-img">
                <span class="logo-text">OSHC-ARMS</span>
            </div>
            <h1>Accreditation Reporting and Monitoring System</h1>
        </div>

        {{-- Body --}}
        <div class="body">
            <div class="icon-circle">✅</div>
            <h2>Interview Schedule Confirmed</h2>
            <p>
                Dear <strong>{{ $application->user->name }}</strong>,
            </p>
            <p>
                We are pleased to inform you that your application has successfully passed the document evaluation phase and you are now scheduled for an interview.
            </p>

            <div class="tracking-card">
                <p class="label">Your Tracking Number</p>
                <p class="value">{{ $application->tracking_number }}</p>

                <p class="label">Current Status</p>
                <p class="value-status">Scheduled for Interview</p>
            </div>

            <p>Please review your interview details below.</p>

            {{-- Interview Details --}}
            <div class="doc-list">
                <p class="doc-list-title">📅 Interview Details</p>
                
                <div class="doc-item">
                    <div class="doc-item-name">Date & Time</div>
                    <div class="doc-item-remark">
                        <span>Date:</span> {{ \Carbon\Carbon::parse($interview->interview_date)->format('l, F j, Y') }}<br>
                        <span>Time:</span> {{ \Carbon\Carbon::parse($interview->interview_time)->format('h:i A') }}
                    </div>
                </div>

                <div class="doc-item">
                    <div class="doc-item-name">Mode & Venue</div>
                    <div class="doc-item-remark">
                        <span>Mode:</span> {{ $interview->mode === 'online' ? 'Online Interview' : 'Face-to-Face' }}<br>
                        @if($interview->mode === 'f2f' && $interview->venue)
                        <span>Venue:</span> {{ $interview->venue }}
                        @endif
                    </div>
                </div>
            </div>

            @if($interview->mode === 'online')
            <p style="font-size:0.85rem; color:#1a6fbd; background-color:#e6f2ff; padding:12px; border-radius:8px; border:1px solid #b3d7ff;">
                <strong>Note:</strong> As your interview is scheduled online, a separate email containing the meeting link and precise instructions will be sent to you shortly.
            </p>
            @else
            <p style="font-size:0.85rem; color:#555;">
                Please ensure you arrive at the venue at least 15 minutes before your scheduled time and bring any original documents that may be required for verification.
            </p>
            @endif

            <div class="btn-wrap">
                <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-track">
                    Track Application
                </a>
            </div>

            <p style="font-size:0.85rem; color:#888;">
                If you have any questions or need to request a reschedule, please contact our office as soon as possible.
            </p>
        </div>

        {{-- Footer --}}
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
