@extends('emails.layout')

@section('title', 'Accreditation Expiring — ARMS')

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

@section('content')
    <div class="icon-circle">⚠️</div>
    <h2>Accreditation Expiring in {{ ucfirst($period) }}</h2>
    <p>
        Hello <strong>{{ $accreditation->user->name }}</strong>, this is a reminder that your accreditation is
        approaching its expiration date. Please take action to renew your accreditation before it expires.
    </p>

    <div class="details-box">
        <h3>Accreditation Details</h3>
        <p><strong>Accreditation No:</strong> {{ $accreditation->accreditation_number }}</p>
        <p><strong>Date of Accreditation:</strong> {{ $accreditation->date_of_accreditation->format('F d, Y') }}</p>
        <p><strong>Validity Date:</strong> {{ $accreditation->validity_date->format('F d, Y') }}</p>
        <p>
            <strong>Urgency:</strong>
            <span class="urgency-badge {{ $period === '1 month' ? 'urgency-1-month' : ($period === '2 months' ? 'urgency-2-months' : 'urgency-3-months') }}">
                Expires in {{ $period }}
            </span>
        </p>
    </div>

    <p>
        To avoid disruption of your accreditation status, we recommend that you begin the <strong>renewal process</strong>
        as soon as possible through your ARMS applicant portal.
    </p>

    <div class="btn-wrap">
        <a href="{{ route('login') }}" class="btn-primary">
            Login to ARMS
        </a>
    </div>

    <p style="font-size: 0.85rem; color: #777;">
        If you have already submitted a renewal application, please disregard this notice. For any questions, please
        contact the OSHC office.
    </p>
@endsection
