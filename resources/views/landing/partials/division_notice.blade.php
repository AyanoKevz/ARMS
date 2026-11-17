{{--
    Tracking page: notices on an OSH Practitioner / Consultant application —
    the 5-working-day re-upload window (documents while For Update, a
    rejected proof of payment while Awaiting Payment), the passed interview
    while For Recommendation, the accreditation once Approved, and the
    archive notice (with the date they may apply again) once it is Rejected.

    Expects $application.
--}}
@use('App\Services\PractitionerArchiveService', 'Archive')
@php
    $noticeStatus = $application->latestStatus?->status?->name;
    $dueDate      = in_array($noticeStatus, ['For Update', 'Awaiting Payment'], true) ? $application->compliance_due_date : null;
    $forPayment   = $noticeStatus === 'Awaiting Payment';
    $archived     = $noticeStatus === 'Rejected' && $application->archived_at;
    $reason       = $application->archive_reason;
    $passed       = $noticeStatus === 'For Recommendation';
    $accredited   = $noticeStatus === 'Approved' ? $application->accreditation : null;
@endphp

@if($dueDate || $archived || $passed || $accredited)
@push('styles')
<link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/track-notice.css') }}">
@endpush

@if($accredited)
<div class="tn-card is-success">
    <div class="tn-head">
        <i class="bi bi-patch-check-fill"></i>
        {{ $accredited->released_at ? 'Certificate and ID Card Ready for Pickup' : 'Accredited — Certificate and ID Card Being Prepared' }}
    </div>
    <div class="tn-body">
        You are now accredited as an {{ $application->accreditationTitle() }}.
        Accreditation No. <strong>{{ $accredited->accreditation_number }}</strong>, valid until
        <strong>{{ $accredited->validity_date->format('F j, Y') }}</strong>.
        {{ $accredited->released_at
            ? 'Your Certificate of Accreditation and Identification Card are ready. Please claim them at the Accreditation Division office during regular office hours.'
            : 'We will email you once your Certificate of Accreditation and Identification Card are ready for pickup.' }}
    </div>
</div>
@elseif($passed)
<div class="tn-card is-success">
    <div class="tn-head"><i class="bi bi-patch-check-fill"></i> Interview Passed — For Recommendation</div>
    <div class="tn-body">
        Congratulations on passing the panel interview! Your application is being recommended for approval to the
        Office of the Executive Director. We will email you the payment instructions
        ({{ $application->paymentAmount() }} Pesos) once it is approved.
    </div>
</div>
@elseif($dueDate)
<div class="tn-card is-warning">
    <div class="tn-head"><i class="bi bi-alarm-fill"></i> Re-upload Deadline: {{ $dueDate->format('l, F j, Y') }}</div>
    <div class="tn-body">
        {{ $forPayment ? 'Your proof of payment was rejected.' : 'Some of your documents need to be corrected.' }}
        Re-upload {{ $forPayment ? 'it' : 'them' }} below within 5 working days — by
        <strong>{{ $dueDate->format('F j, Y') }}</strong>. If {{ $forPayment ? 'it is' : 'they are' }} not received by then, your application will be
        archived and you may apply again only after 1 month.
    </div>
</div>
@else
<div class="tn-card is-danger">
    <div class="tn-head">
        <i class="bi bi-archive-fill"></i>
        {{ $reason === Archive::INTERVIEW_FAILED ? 'Interview Result: Did Not Pass' : 'Application Archived' }}
    </div>
    <div class="tn-body">
        {{ match ($reason) {
            Archive::INTERVIEW_FAILED       => 'You did not pass the panel interview, so this application has been closed and archived.',
            Archive::PAYMENT_NON_COMPLIANCE => 'The corrected proof of payment was not received within 5 working days, so this application has been closed and archived.',
            Archive::NON_COMPLIANCE         => 'The corrected documents were not received within 5 working days, so this application has been closed and archived.',
            default                         => 'This application will not proceed and has been closed and archived.',
        } }}
        @if($application->reapply_after)
            You may submit a new application on or after <strong>{{ $application->reapply_after->format('F j, Y') }}</strong>.
        @else
            Please contact the Accreditation Division for assistance.
        @endif
    </div>
</div>
@endif
@endif
