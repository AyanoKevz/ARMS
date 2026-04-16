<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email — ARMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
        }
        .header {
            background: linear-gradient(135deg, #1a2e5a 0%, #0d1f42 100%);
            padding: 36px 40px 28px;
            text-align: center;
        }
        
        .header .logo-badge span {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 2px;
        }
        .header h1 {
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 600;
            margin-top: 4px;
            opacity: 0.9;
            color: #D4AC4B;
        }
        .body {
            padding: 40px 48px;
            background-color: #f9f9f9;
        }
        .body .icon-circle {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #e8f0fe, #d2e3fc);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 2.25rem;
            align-items: center;
            justify-content: center;
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
        .body .email-highlight {
            display: inline-block;
            background: #f0f4ff;
            border: 1px solid #c8d8fc;
            border-radius: 6px;
            padding: 2px 10px;
            font-weight: 600;
            color: #1a2e5a;
        }
        .btn-wrap {
            text-align: center;
            margin: 32px 0 28px;
        }
        .btn-verify {
            display: inline-block;
            background: linear-gradient(135deg, #D4AC4B, #b8922e);
            color: #1a2e5a;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 10px;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 16px rgba(212,172,75,0.35);
        }
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
            color: #ffffff;
            text-decoration: underline;
            text-align: center;
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
                <span>OSHC-ARMS</span>
            </div>
            <h1>Accreditation Reporting and Monitoring System</h1>
        </div>

        {{-- Body --}}
        <div class="body">
            <div class="icon-circle">✉️</div>
            <h2>Verify Your Email Address</h2>
            <p>
                Thank you for registering with ARMS. To complete your application submission,
                please verify that <span class="email-highlight">{{ $applicantEmail }}</span> is your email address.
            </p>
            <p>Click the button below to confirm your email and officially submit your registration:</p>

            <div class="btn-wrap">
                <a href="{{ $verificationUrl }}" class="btn-verify">
                    Verify Email &amp; Submit Application
                </a>
            </div>

            <div class="link-fallback">
                <p>If the button above does not work, copy and paste this link into your browser:</p>
                <a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a>
            </div>
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
