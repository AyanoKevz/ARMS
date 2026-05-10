@extends('emails.layout')

@section('title', 'Instructor Credentials Update Requested — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #fff8e1, #ffe082);
        }

        .body p {
            text-align: left;
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
@endsection

@section('content')
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
@endsection
