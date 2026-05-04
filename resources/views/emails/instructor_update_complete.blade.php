<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Credentials Update Approved — ARMS</title>
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
            padding: 30px 20px;
            text-align: center;
        }

        .logo-badge { display: inline-block; margin-bottom: 8px; }

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

        .icon-circle {
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
            margin-bottom: 16px;
        }

        .info-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-left: 4px solid #27ae60;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .info-box p {
            margin-bottom: 4px;
            font-size: 0.9rem;
            color: #1a5c2e;
        }

        .info-box p:last-child { margin-bottom: 0; }

        .note {
            font-size: 0.83rem;
            color: #999;
            text-align: center;
            margin-top: 8px;
        }

        .footer {
            background: #f7f8fa;
            border-top: 1px solid #eee;
            padding: 24px 40px;
            text-align: center;
        }

        .footer p { font-size: 0.78rem; color: #aaa; line-height: 1.6; }
        .footer strong { color: #888; }
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
            <h2>Instructor Credentials Update Approved</h2>

            <p>
                Dear <strong>{{ $instructor->user->name }}</strong>,
            </p>

            <p>
                We are pleased to inform you that the updated credentials for your instructor,
                <strong>{{ $instructor->first_name }} {{ $instructor->last_name }}</strong>,
                have been reviewed and <strong>approved</strong> by the OSHC administration.
            </p>

            <div class="info-box">
                <p><strong>Instructor:</strong> {{ $instructor->first_name }} {{ $instructor->last_name }}</p>
                <p><strong>Status:</strong> Credentials Approved ✅</p>
                <p><strong>Action:</strong> No further action required</p>
            </div>

            <p>
                Your instructor's credentials are now up to date in our system.
                Thank you for promptly completing the update request.
            </p>

            <p class="note">
                If you have any questions, please contact the OSHC HCD support team.
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
