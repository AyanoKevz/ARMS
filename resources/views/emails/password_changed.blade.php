<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Changed Successfully — ARMS</title>
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
            background: linear-gradient(135deg, #e6fffa, #b2f5ea);
            border-radius: 50%;
            margin: 0 auto 24px;
            font-size: 2.5rem;
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
            margin-bottom: 20px;
        }

        .login-info {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }

        .login-info h3 {
            font-size: 1rem;
            color: #1a2e5a;
            margin-bottom: 12px;
            border-bottom: 1px solid #edf2f7;
            padding-bottom: 8px;
        }

        .login-info p {
            text-align: left;
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: #4a5568;
        }

        .btn-wrap {
            text-align: center;
            margin: 32px 0 28px;
        }

        .btn-login {
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
            <h2>Password Changed!</h2>
            <p>
                Hello <strong>{{ $user->name }}</strong>, your account password has been successfully updated.
            </p>

            <div class="login-info">
                <h3>Security Details</h3>
                <p><strong>Account:</strong> {{ $user->email }}</p>
                <p><strong>Action:</strong> Password Change</p>
                <p><strong>Status:</strong> Completed Successfully</p>
            </div>

            <p>
                You can now log in to your account using your new password.
            </p>

            <div class="btn-wrap">
                <a href="{{ route('login') }}" class="btn-login">
                    Login to ARMS
                </a>
            </div>
            
            <p style="font-size: 0.85rem; color: #777;">
                If you did not make this change, please contact our support team immediately or reset your password again to secure your account.
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
