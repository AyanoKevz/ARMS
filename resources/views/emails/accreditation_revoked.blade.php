@extends('emails.layout')

@section('title', 'Accreditation Revoked — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #fee2e2, #fca5a5);
            font-size: 2.5rem;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #ffffff;
            background-color: #dc2626;
        }
@endsection

@section('content')
    <div class="icon-circle">🚫</div>
    <h2>Accreditation Revoked</h2>
    <p>
        Hello <strong>{{ $accreditation->user->name ?? $accreditation->user->email }}</strong>, we regret to inform you that your accreditation
        has been revoked by the OSHC administration.
    </p>

    <div class="details-box">
        <h3>Accreditation Details</h3>
        <p><strong>Accreditation No:</strong> {{ $accreditation->accreditation_number }}</p>
        <p><strong>Accreditation Type:</strong> {{ $accreditation->accreditationType->name ?? 'N/A' }}</p>
        <p>
            <strong>Status:</strong>
            <span class="status-badge">Revoked</span>
        </p>
    </div>

    <p class="info-box red">
        <strong>Important:</strong> You are no longer authorized to operate under this accreditation. 
        If you wish to restore your status in the future, you must apply for <strong>reinstatement</strong> 
        through the ARMS portal.
    </p>

    <p style="margin-top: 20px;">
        For any questions or clarifications regarding this decision, please contact the OSHC office immediately.
    </p>

    <div class="btn-wrap">
        <a href="{{ route('login') }}" class="btn-primary">
            Login to ARMS
        </a>
    </div>
@endsection
