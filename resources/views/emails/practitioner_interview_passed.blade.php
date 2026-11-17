@extends('emails.layout')

@section('title', 'Interview Passed — ARMS')

@section('css')
        .icon-circle.passed {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        }
@endsection

@section('content')

    <div class="icon-circle passed">🎉</div>
    <h2>Congratulations! You Passed the Interview</h2>
    <p>
        Dear <strong>{{ $application->user->name ?? $application->user->email }}</strong>,
    </p>
    <p>
        Congratulations on passing the evaluation and panel interview for your accreditation as
        an {{ $application->accreditationTitle() }}!
    </p>
    <p>
        Your application is now being recommended for approval to the Office of the Executive Director.
    </p>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>
        <p class="label">Application Status</p>
        <p class="value-status">✅ Passed — For Recommendation</p>
    </div>

    <p class="info-box blue">
        <strong>Next Step:</strong> Once your recommendation is approved, we will email you the payment
        instructions for the accreditation fee of <strong>{{ $application->paymentAmount() }} Pesos</strong>.
        No action is needed from you yet.
    </p>

    <div class="btn-wrap">
        <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-primary">
            Track My Application
        </a>
    </div>

    <p>
        Thank you, and we look forward to your cooperation.
    </p>

@endsection
