@extends('emails.layout')

@section('title', 'Instructor Credentials Update Approved — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        }

        .body p {
            text-align: left;
        }

        .info-box.green {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-left: 4px solid #27ae60;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .info-box.green p {
            margin-bottom: 4px;
            font-size: 0.9rem;
            color: #1a5c2e;
        }

        .info-box.green p:last-child { margin-bottom: 0; }
@endsection

@section('content')
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

    <div class="info-box green">
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
@endsection
