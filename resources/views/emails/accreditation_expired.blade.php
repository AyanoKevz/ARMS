@extends('emails.layout')

@section('title', 'Accreditation Expired — ARMS')

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
            background-color: #ef4444;
        }
@endsection

@section('content')
    <div class="icon-circle">❌</div>
    <h2>Accreditation Expired</h2>
    <p>
        Hello <strong>{{ $accreditation->user->name }}</strong>, we regret to inform you that your accreditation
        has expired as of <strong>{{ $accreditation->validity_date->format('F d, Y') }}</strong>.
    </p>

    <div class="details-box">
        <h3>Accreditation Details</h3>
        <p><strong>Accreditation No:</strong> {{ $accreditation->accreditation_number }}</p>
        <p><strong>Date of Accreditation:</strong> {{ $accreditation->date_of_accreditation->format('F d, Y') }}</p>
        <p><strong>Validity Date:</strong> {{ $accreditation->validity_date->format('F d, Y') }}</p>
        <p>
            <strong>Status:</strong>
            <span class="status-badge">Expired</span>
        </p>
    </div>

    <p>
        Your accreditation status has been updated to <strong>Expired</strong>. To restore your accreditation,
        you may apply for <strong>reinstatement</strong> through the ARMS applicant portal.
    </p>

    <div class="btn-wrap">
        <a href="{{ route('login') }}" class="btn-primary">
            Login to ARMS
        </a>
    </div>

    <p style="font-size: 0.85rem; color: #777;">
        For any questions regarding the reinstatement process, please contact the OSHC office.
    </p>
@endsection
