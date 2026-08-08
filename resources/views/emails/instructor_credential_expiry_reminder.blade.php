@extends('emails.layout')

@section('title', 'Instructor Credential Expiring — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #fefce8, #fde68a);
            font-size: 2.5rem;
        }

        .urgency-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #ffffff;
        }

        .urgency-3-months {
            background-color: #f59e0b;
        }

        .urgency-2-months {
            background-color: #f97316;
        }

        .urgency-1-month {
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
    <div class="icon-circle">⚠️</div>
    <h2>Instructor Credential Expiring in {{ ucfirst($period) }}</h2>
    <p>
        Hello <strong>{{ $recipientName }}</strong>, this is a reminder that an instructor credential for 
        <strong>{{ $instFullName }}</strong> is approaching its expiration date. Please upload an updated certificate before it expires to maintain active compliance.
    </p>

    <div class="details-box">
        <h3>Credential Details</h3>
        <p><strong>Instructor Name:</strong> {{ $instFullName }}</p>
        <p><strong>Credential Type:</strong> {{ $credName }}</p>
        @if($credential->number)
            <p><strong>Certificate Number:</strong> {{ $credential->number }}</p>
        @endif
        @if($credential->issued_date)
            <p><strong>Issued Date:</strong> {{ \Carbon\Carbon::parse($credential->issued_date)->format('F d, Y') }}</p>
        @endif
        <p><strong>Validity Date:</strong> {{ \Carbon\Carbon::parse($credential->validity_date)->format('F d, Y') }}</p>
        <p>
            <strong>Urgency:</strong>
            <span class="urgency-badge {{ $period === '1 month' ? 'urgency-1-month' : ($period === '2 months' ? 'urgency-2-months' : 'urgency-3-months') }}">
                Expires in {{ $period }}
            </span>
        </p>
    </div>

    <p>
        To keep your instructor credentials up-to-date, please submit updated certificate documents through your <strong>ARMS applicant portal</strong>.
    </p>

    <div class="btn-wrap">
        <a href="{{ route('login') }}" class="btn-primary">
            Login to ARMS Portal
        </a>
    </div>

    <p style="font-size: 0.85rem; color: #777;">
        If you have already uploaded the updated credential PDF, please disregard this notice. For any questions, please contact the OSHC office.
    </p>
@endsection
