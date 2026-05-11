@extends('emails.layout')

@section('title', 'Application Result — ARMS')

@section('css')
        .icon-circle.passed {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        }

        .icon-circle.failed {
            background: linear-gradient(135deg, #fde8e8, #fcc5c5);
        }
@endsection

@section('content')

    @if($result === 'passed')
    {{-- ════════════════ PASSED ════════════════ --}}

    <div class="icon-circle passed">🎉</div>
    <h2>Congratulations! Application Approved</h2>
    <p>
        Dear <strong>{{ $application->user->name ?? $application->user->email }}</strong>,
    </p>
    <p>
        We are delighted to inform you that you have <strong>successfully passed</strong> the application process
        for your accreditation. You are now officially accredited.
    </p>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>
        <p class="label">Application Status</p>
        <p class="value-status">✅ Approved – Accredited</p>
    </div>

    @if($accreditation)
    <div class="doc-list">
        <p class="doc-list-title">🏅 Your Accreditation Details</p>

        <div class="doc-item green">
            <div class="doc-item-name">Accreditation Number</div>
            <div class="doc-item-value">{{ $accreditation->accreditation_number }}</div>
        </div>

        <div class="doc-item green">
            <div class="doc-item-name">Date of Accreditation</div>
            <div class="doc-item-value">{{ $accreditation->date_of_accreditation->format('F d, Y') }}</div>
        </div>

        <div class="doc-item green">
            <div class="doc-item-name">Valid Until</div>
            <div class="doc-item-value">{{ $accreditation->validity_date->format('F d, Y') }}</div>
        </div>

        <div class="doc-item green">
            <div class="doc-item-name">Accreditation Type</div>
            <div class="doc-item-value">{{ $application->accreditationType->name ?? 'N/A' }}</div>
        </div>
    </div>

    <p class="info-box blue">
        <strong>Note:</strong> Please keep your accreditation number on record. You will need it for all 
        future transactions with OSHC. Your accreditation is valid for <strong>3 years</strong> from the date of issue.
    </p>
    @endif

    <div class="btn-wrap">
        <a href="{{ url('/track-application?tracking_number=' . $application->tracking_number) }}" class="btn-primary">
            View My Accreditation
        </a>
    </div>

    <p style="font-size:0.85rem; color:#888;">
        If you have any questions regarding your accreditation, please contact our office.
    </p>

    @else
    {{-- ════════════════ NOT PASSED ════════════════ --}}

    <div class="icon-circle failed">❌</div>
    <h2>Application Result: Not Passed</h2>
    <p>
        Dear <strong>{{ $application->user->name ?? $application->user->email }}</strong>,
    </p>
    <p>
        Thank you for your time and effort in completing the application process. After careful evaluation,
        we regret to inform you that your application has <strong>not passed</strong> the requirements
        at this time.
    </p>

    <div class="tracking-card">
        <p class="label">Tracking Number</p>
        <p class="value">{{ $application->tracking_number }}</p>
        <p class="label">Application Status</p>
        <p class="value-status">❌ Rejected – Not Passed</p>
    </div>

    <p>
        We understand this may be disappointing. We encourage you to review the accreditation requirements
        and consider re-applying in the future.
    </p>

    <p class="info-box red">
        <strong>Next Steps:</strong> You may re-apply for accreditation once you have addressed any 
        gaps identified during the evaluation process. Please contact our office for guidance on the 
        re-application procedure.
    </p>

    <p style="font-size:0.85rem; color:#888; margin-top: 16px;">
        If you believe this decision was made in error or have questions, please reach out to our office 
        as soon as possible.
    </p>

    @endif

@endsection
