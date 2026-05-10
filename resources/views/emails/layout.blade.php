<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'OSHC-ARMS')</title>
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

        .body .icon-circle {
            width: 72px;
            height: 72px;
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

        /* ── Details Box ── */
        .details-box {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }

        .details-box h3 {
            font-size: 1rem;
            color: #1a2e5a;
            margin-bottom: 12px;
            border-bottom: 1px solid #edf2f7;
            padding-bottom: 8px;
        }

        .details-box p {
            text-align: left;
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: #4a5568;
        }

        /* ── Doc/Item Lists ── */
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

        .doc-item-remark {
            font-size: 0.85rem;
            color: #27ae60;
        }

        .doc-item-remark span {
            font-weight: 600;
        }

        .doc-item-value {
            font-size: 0.88rem;
            color: #27ae60;
            font-weight: 600;
        }

        /* ── Email Highlight ── */
        .email-highlight {
            display: inline-block;
            background: #f0f4ff;
            border: 1px solid #c8d8fc;
            border-radius: 6px;
            padding: 2px 10px;
            font-weight: 600;
            color: #1a2e5a;
        }

        /* ── Info Box ── */
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

        /* ── CTA Button ── */
        .btn-wrap {
            text-align: center;
            margin: 32px 0 28px;
        }

        .btn-primary {
            display: inline-block;
            background: linear-gradient(135deg, #D4AC4B, #b8922e);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 10px;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 16px rgba(26, 46, 90, 0.25);
        }

        /* ── Link Fallback ── */
        .link-fallback {
            background: #f7f8fa;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 28px;
        }

        .link-fallback p {
            text-align: left;
            font-size: 0.82rem;
            color: #888;
            margin-bottom: 6px;
        }

        .link-fallback a {
            display: block;
            word-break: break-all;
            font-size: 0.78rem;
            text-decoration: underline;
            text-align: center;
        }

        /* ── Notice ── */
        .notice {
            background: #fff8e6;
            border: 1px solid #f5d98a;
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 0.85rem;
            color: #7a5c00;
            text-align: center;
            margin-bottom: 28px;
        }

        .note {
            font-size: 0.83rem;
            color: #999;
            text-align: center;
            margin-top: 8px;
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

        /* ── Per-email overrides injected via @section('css') ── */
        @yield('css')
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
            @yield('content')
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
