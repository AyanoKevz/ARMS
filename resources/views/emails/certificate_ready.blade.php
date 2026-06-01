@extends('emails.layout')

@section('title', 'Ready for Pickup: Accreditation Certificate — ARMS')

@section('css')
        .icon-circle.certificate {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        }
@endsection

@section('content')

    <div class="icon-circle certificate">🎓</div>
    <h2>Accreditation Certificate Ready for Pickup</h2>
    <p>
        Dear <strong>{{ $application->user->name ?? $application->user->email }}</strong>,
    </p>
    <p>
        We are pleased to inform you that your Accreditation Certificate for <strong>{{ $application->accreditationType->name ?? 'Accreditation' }}</strong> is now signed, finalized, and <strong>ready for pickup</strong>!
    </p>
    <p>
        Please coordinate with the Health Control Division (HCD) office during regular office hours to claim your original physical certificate.
    </p>

    <div class="tracking-card">
        <p class="label">Accreditation Number</p>
        <p class="value">{{ $application->accreditation->accreditation_number }}</p>
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>
        <p class="label">Status</p>
        <p class="value-status">✅ Ready for Pickup</p>
    </div>

    <p class="info-box green">
        <strong>Important:</strong> Please bring a valid government-issued ID and/or an authorization letter if claiming on behalf of the accredited entity.
    </p>

    <div class="btn-wrap">
        <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-primary" style="background-color: #2e7d32 !important; border-color: #2e7d32 !important;">
            Track Application Status
        </a>
    </div>

    <p style="font-size:0.85rem; color:#888;">
        For any questions regarding release schedules or requirements, please contact our HCD office.
    </p>

@endsection
