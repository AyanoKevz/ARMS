@extends('emails.layout')

@section('title', 'Instructor Credential Expired — ARMS')

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

@php
    $credLabels = [
        'EMS'  => 'TESDA Emergency Medical Services NC II or III Certificate',
        'TM1'  => 'TESDA Trainers Methodology Certificate 1',
        'NTTC' => 'TESDA National TVET Trainer Certificate',
        'BOSH' => 'BOSH SO1 or SO2 Certificate',
    ];
    $credName = $credLabels[$credential->type] ?? $credential->type;
    $instructor = $credential->instructor;
    $instFullName = trim(($instructor->first_name ?? '') . ' ' . ($instructor->middle_name ?? '') . ' ' . ($instructor->last_name ?? ''));
    $recipientUser = $instructor->user ?? ($instructor->application ? $instructor->application->user : null);
    $recipientName = $recipientUser ? $recipientUser->name : 'Applicant';
@endphp

@section('content')
    <div class="icon-circle">❌</div>
    <h2>Instructor Credential Expired</h2>
    <p>
        Hello <strong>{{ $recipientName }}</strong>, we are notifying you that an instructor credential for 
        <strong>{{ $instFullName }}</strong> has expired as of <strong>{{ \Carbon\Carbon::parse($credential->validity_date)->format('F d, Y') }}</strong>.
    </p>

    <div class="details-box">
        <h3>Credential Details</h3>
        <p><strong>Instructor Name:</strong> {{ $instFullName }}</p>
        <p><strong>Credential Type:</strong> {{ $credName }}</p>
        @if($credential->number)
            <p><strong>Certificate Number:</strong> {{ $credential->number }}</p>
        @endif
        <p><strong>Validity Date:</strong> {{ \Carbon\Carbon::parse($credential->validity_date)->format('F d, Y') }}</p>
        <p>
            <strong>Status:</strong>
            <span class="status-badge">Expired</span>
        </p>
    </div>

    <p>
        Please upload an updated valid certificate in your <strong>ARMS applicant portal</strong> to restore active compliance.
    </p>

    <div class="btn-wrap">
        <a href="{{ route('login') }}" class="btn-primary">
            Login to ARMS Portal
        </a>
    </div>

    <p style="font-size: 0.85rem; color: #777;">
        For any questions regarding instructor credentials, please contact the OSHC office.
    </p>
@endsection
