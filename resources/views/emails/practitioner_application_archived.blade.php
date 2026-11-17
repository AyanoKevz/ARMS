@extends('emails.layout')

@section('title', ($interviewFailed ? 'Interview Result: Did Not Pass' : 'Application Archived') . ' — ARMS')

@section('css')
    .icon-circle {
        background: linear-gradient(135deg, #fde8e8, #fcc5c5);
    }
@endsection

@section('content')
    <div class="icon-circle">{{ $interviewFailed ? '📋' : '🗂️' }}</div>

    <h2>{{ $interviewFailed ? 'Interview Result: Did Not Pass' : 'Your Application Has Been Archived' }}</h2>

    <p>
        Dear <strong>{{ $application->user->name ?? $application->user->email }}</strong>,
    </p>

    @if($interviewFailed)
        <p>
            Thank you for attending the panel interview for your
            <strong>{{ $application->accreditationType->name ?? 'accreditation' }}</strong> application.
            We regret to inform you that you <strong>did not pass</strong> the interview, so your application
            has been closed and archived.
        </p>
    @elseif($paymentLapsed)
        <p>
            Your proof of payment for your <strong>{{ $application->accreditationType->name ?? 'accreditation' }}</strong>
            application was rejected and needed to be re-uploaded within <strong>{{ $workingDays }} working days</strong>.
            As no corrected proof of payment was received by the deadline, your application has been closed and archived.
        </p>
    @else
        <p>
            Your <strong>{{ $application->accreditationType->name ?? 'accreditation' }}</strong> application had
            documents that needed to be re-uploaded within <strong>{{ $workingDays }} working days</strong>.
            As no corrected documents were received by the deadline, your application has been closed and archived.
        </p>
    @endif

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>
        <p class="label">Application Status</p>
        <p class="value-status">{{ $interviewFailed ? 'Rejected — Did Not Pass' : 'Rejected — Archived' }}</p>
    </div>

    <p class="info-box red">
        <strong>Applying again:</strong> you may submit a new application after {{ $months }} {{ \Illuminate\Support\Str::plural('month', $months) }},
        on or after <strong>{{ $application->reapply_after?->format('F j, Y') }}</strong>. The archived application
        and its documents will be deleted on that date.
    </p>

    <p style="font-size:0.85rem; color:#888;">
        If you have any questions, please contact our office directly.
    </p>
@endsection
