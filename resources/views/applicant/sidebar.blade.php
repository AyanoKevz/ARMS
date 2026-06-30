<!-- Applicant Sidebar -->
@php
    $hasOngoingRenewal = \App\Models\Application::where('user_id', auth()->id())
        ->whereIn('application_type', ['renewal', 'reinstatement'])
        ->whereHas('latestStatus', function ($q) {
            $q->whereHas('status', function ($q2) {
                $q2->whereIn('name', [
                    'Submitted',
                    'Under Evaluation',
                    'For Update',
                    'Scheduled for Interview',
                    'Awaiting Payment',
                    'Payment Verification',
                ]);
            });
        })
        ->exists();
@endphp

<li id="tour-step-dashboard"><a href="{{ route('applicant.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard </a></li>
<li id="tour-step-profile"><a href="{{ route('profile.index') }}"><i class="fas fa-user-circle"></i> My Profile </a></li>

<li id="tour-step-submission"><a><i class="fas fa-file-invoice"></i> Submission report <span class="fa fa-chevron-down"></span></a>
    <ul class="nav child_menu">
        @if(!$hasOngoingRenewal)
            <li><a href="{{ route('applicant.ntc.index') }}">Notice to Conduct</a></li>
        @else
            <li><a href="javascript:void(0);" onclick="alert('You cannot submit or access the Notice to Conduct while your renewal/reinstatement application is ongoing.')" style="opacity: 0.6; cursor: not-allowed;"><i class="fas fa-lock" style="margin-right: 5px;"></i> Notice to Conduct</a></li>
        @endif
        <li><a href="#">Report to Changes</a></li>
        <li><a href="#">Post Training Report</a></li>
    </ul>
</li>
<li id="tour-step-renewal"><a href="{{ route('applicant.renewal.index') }}"><i class="fas fa-sync-alt"></i> Renewal / Reinstatement </a></li>
<li id="tour-step-instructors"><a href="{{ route('applicant.instructors.index') }}"><i class="fas fa-chalkboard-teacher"></i> FATPRO Instructor </a></li>