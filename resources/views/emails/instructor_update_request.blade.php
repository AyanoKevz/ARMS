<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Credentials Update Requested — ARMS</title>
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
            background: linear-gradient(135deg, #fff8e1, #ffe082);
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

        .reason-box {
            background: #fff8e1;
            border: 1px solid #ffe082;
            border-left: 4px solid #D4AC4B;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .reason-box .label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9a7a1a;
            margin-bottom: 6px;
        }

        .reason-box .value {
            font-size: 0.95rem;
            color: #5a4a0a;
            line-height: 1.6;
        }

        .fields-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .fields-box .label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1a2e5a;
            margin-bottom: 10px;
        }

        .field-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f4f8;
            font-size: 0.92rem;
            color: #333;
        }

        .field-item:last-child { border-bottom: none; }

        .field-dot {
            height: 8px;
        }

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
            box-shadow: 0 4px 16px rgba(26,46,90,0.25);
        }

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
            <div class="icon-circle">📋</div>
            <h2>Instructor Credentials Update Required</h2>

            <p>
                Dear <strong>{{ $instructor->user->name }}</strong>,
            </p>

            <p>
                The OSHC administration is requesting an update to the credentials for your instructor,
                <strong>{{ $instructor->first_name }} {{ $instructor->last_name }}</strong>.
                Please log in to your applicant portal to upload the requested documents.
            </p>

            {{-- Fields and Reasons to update --}}
            @php
                $fieldLabels = [
                    'service_agreement' => 'Service Agreement between FATPro head and instructor',
                    'EMS'  => 'TESDA Emergency Medical Services NC II or III Certificate',
                    'TM1'  => 'TESDA Trainers Methodology Certificate 1',
                    'NTTC' => 'TESDA National TVET Trainer Certificate',
                    'BOSH' => 'BOSH SO1 or SO2 Certificate',
                ];
                $fields = $instructor->update_request_fields ?? [];
                $reasons = json_decode($instructor->update_request_reason, true);
            @endphp

            @if(count($fields) > 0)
            <div class="fields-box">
                <div class="label" style="margin-bottom: 15px;">Documents to Update</div>
                @foreach($fields as $field)
                @php
                    $reasonText = is_array($reasons) ? ($reasons[$field] ?? 'No reason provided') : $instructor->update_request_reason;
                @endphp
                <div class="field-item" style="margin-bottom: 12px;">
                    <div style="font-weight: bold; margin-bottom: 4px;">
                        <div class="field-dot"></div>
                        {{ $fieldLabels[$field] ?? $field }}
                    </div>
                    <div style="margin-left: 18px; color: #555; font-size: 0.9em; background: #f8fafc; padding: 6px 10px; border-left: 2px solid #ccc;">
                        Reason: {{ $reasonText }}
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            <p>
                Please log in to your portal to upload the replacement files.
                Uploaded files will replace the existing ones automatically.
            </p>

            <div class="btn-wrap">
                <a href="{{ route('applicant.instructors.show', $instructor->id) }}" class="btn-primary">
                    Go to Instructor Details
                </a>
            </div>

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
