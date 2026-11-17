@php
    $isAdminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';

    // Base flags
    $routeShow = request()->routeIs('admin.accreditation.applications.show');
    $appObj = isset($application) ? $application : null;

    $appStatus = $appObj?->latestStatus?->status?->name;
    $accStatus = $appObj?->accreditation?->status;

    // Recommendation/Payment lists For Recommendation too.
    $isPaymentApp = in_array($appStatus, ['For Recommendation', 'Awaiting Payment', 'Payment Verification']);
    $isReleasingApp = in_array($appStatus, ['Approved']);

    // Determining which menu item is active

    // 1. Dashboard
    $dashActive = request()->routeIs('admin.accreditation.dashboard');

    // 2. Profile
    $profileActive = request()->routeIs('profile.index');

    // 3. Admin List
    $adminListActive = request()->routeIs('admin.accreditation.directory.admins');

    // 4. Recommendation/Payment (FATPro)
    $paymentActive = request()->routeIs('admin.accreditation.applications.awaiting_payment')
        || ($routeShow && $isPaymentApp)
        || (request()->routeIs('admin.accreditation.practitioner.show') && $isPaymentApp);

    // 5. Releasing (Certificate Issuance)
    $releasingActive = request()->routeIs('admin.accreditation.applications.releasing')
        || ($routeShow && $isReleasingApp && !$isPaymentApp)
        || (request()->routeIs('admin.accreditation.practitioner.show') && $isReleasingApp && ! $appObj?->accreditation?->released_at);

    // 6. OSH Practitioner / OSH Consultant. Both open on the practitioner
    // application page, where the entry of the application's own type
    // follows its status.
    $pracShow = request()->routeIs('admin.accreditation.practitioner.show');
    $isConsultantApp = $pracShow && $appObj?->isConsultant();
    $pracPendingActive = request()->routeIs('admin.accreditation.practitioner.new.pending')
        || ($pracShow && ! $isConsultantApp && $appStatus === 'Submitted');
    $pracReviewActive = request()->routeIs('admin.accreditation.practitioner.new.under_review')
        || ($pracShow && ! $isConsultantApp && in_array($appStatus, ['Under Evaluation', 'For Update'], true));
    $pracParentActive = $pracPendingActive || $pracReviewActive;

    $consPendingActive = request()->routeIs('admin.accreditation.consultant.new.pending')
        || ($isConsultantApp && $appStatus === 'Submitted');
    $consReviewActive = request()->routeIs('admin.accreditation.consultant.new.under_review')
        || ($isConsultantApp && in_array($appStatus, ['Under Evaluation', 'For Update'], true));
    $consParentActive = $consPendingActive || $consReviewActive;

    // 7. Practitioner & Consultant lists
    $intSlotsActive = request()->routeIs('admin.accreditation.division.interviews.slots')
        || ($pracShow && $appStatus === 'Scheduled for Interview' && ! $appObj?->interview);
    $intScheduledActive = request()->routeIs('admin.accreditation.division.interviews.scheduled')
        || ($pracShow && $appStatus === 'Scheduled for Interview' && $appObj?->interview);
    $interviewsParentActive = $intSlotsActive || $intScheduledActive;

    $activeAccActive = request()->routeIs('admin.accreditation.division.accreditations.active')
        || ($pracShow && $isReleasingApp && $appObj?->accreditation?->released_at);
    $inactiveAccActive = request()->routeIs('admin.accreditation.division.accreditations.inactive');
    $archivedActive = request()->routeIs('admin.accreditation.division.applications.archived')
        || ($pracShow && ($appStatus === 'Rejected' || $accStatus === 'archived'));
    if ($pracShow && $accStatus === 'archived') {
        $releasingActive = $activeAccActive = false;
    }
@endphp

<!-- Accreditation Portal Sidebar -->
<li class="{{ $dashActive ? 'current-page active' : '' }}"><a href="{{ route('admin.accreditation.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard </a></li>
<li class="{{ $profileActive ? 'current-page active' : '' }}"><a href="{{ route('profile.index') }}"><i class="fas fa-user-circle"></i> My Profile </a></li>
<li class="{{ $adminListActive ? 'current-page active' : '' }}"><a href="{{ route('admin.accreditation.directory.admins') }}"><i class="fas fa-users-cog"></i> Admin List </a></li>

{{-- OSH Practitioner: new applications are open; Renewal / Reinstatement is not yet. --}}
<li class="{{ $pracParentActive ? 'active' : '' }}"><a><i class="fas fa-user-shield"></i> OSH Practitioner <span class="fas fa-chevron-down"></span></a>
    <ul class="nav child_menu {{ $pracParentActive ? 'is-open' : '' }}">
        <li class="nav-subheading">New Application</li>
        <li class="{{ $pracPendingActive ? 'current-page' : '' }}"><a href="{{ route('admin.accreditation.practitioner.new.pending') }}"><i class="fas fa-hourglass-half"></i> Pending</a></li>
        <li class="{{ $pracReviewActive ? 'current-page' : '' }}"><a href="{{ route('admin.accreditation.practitioner.new.under_review') }}"><i class="fas fa-search"></i> Under Review</a></li>
        <li class="nav-subheading">Renewal / Reinstatement</li>
        @include('admin.accreditation._locked_link', ['icon' => 'fa-hourglass-half', 'label' => 'Pending'])
        @include('admin.accreditation._locked_link', ['icon' => 'fa-search', 'label' => 'Under Review'])
    </ul>
</li>

{{-- OSH Consultant: same structure; new applications are open, Renewal / Reinstatement is not yet. --}}
<li class="{{ $consParentActive ? 'active' : '' }}"><a><i class="fas fa-user-tie"></i> OSH Consultant <span class="fas fa-chevron-down"></span></a>
    <ul class="nav child_menu {{ $consParentActive ? 'is-open' : '' }}">
        <li class="nav-subheading">New Application</li>
        <li class="{{ $consPendingActive ? 'current-page' : '' }}"><a href="{{ route('admin.accreditation.consultant.new.pending') }}"><i class="fas fa-hourglass-half"></i> Pending</a></li>
        <li class="{{ $consReviewActive ? 'current-page' : '' }}"><a href="{{ route('admin.accreditation.consultant.new.under_review') }}"><i class="fas fa-search"></i> Under Review</a></li>
        <li class="nav-subheading">Renewal / Reinstatement</li>
        @include('admin.accreditation._locked_link', ['icon' => 'fa-hourglass-half', 'label' => 'Pending'])
        @include('admin.accreditation._locked_link', ['icon' => 'fa-search', 'label' => 'Under Review'])
    </ul>
</li>

{{-- Interviews, across Practitioner and Consultant. --}}
<li class="{{ $interviewsParentActive ? 'active' : '' }}"><a><i class="fas fa-calendar-check"></i> Schedule Interviews <span class="fas fa-chevron-down"></span></a>
    <ul class="nav child_menu {{ $interviewsParentActive ? 'is-open' : '' }}">
        <li class="{{ $intSlotsActive ? 'current-page' : '' }}"><a href="{{ route('admin.accreditation.division.interviews.slots') }}"><i class="fas fa-calendar-plus"></i> Manage Interview Slots</a></li>
        <li class="{{ $intScheduledActive ? 'current-page' : '' }}"><a href="{{ route('admin.accreditation.division.interviews.scheduled') }}"><i class="fas fa-calendar-check"></i> Scheduled Interviews</a></li>
    </ul>
</li>

{{-- Payment and certificate release, for every accreditation type. --}}
<li class="{{ $paymentActive ? 'current-page active' : '' }}"><a href="{{ route('admin.accreditation.applications.awaiting_payment') }}"><i class="fas fa-money-check-alt"></i> Recommendation/Payment </a></li>
<li class="{{ $releasingActive ? 'current-page active' : '' }}"><a href="{{ route('admin.accreditation.applications.releasing') }}"><i class="fas fa-file-signature"></i> Certificate Issuance </a></li>

{{-- Directories, across Practitioner and Consultant. --}}
<li class="{{ $activeAccActive ? 'current-page active' : '' }}"><a href="{{ route('admin.accreditation.division.accreditations.active') }}"><i class="fas fa-certificate"></i> Active Accreditations </a></li>
<li class="{{ $inactiveAccActive ? 'current-page active' : '' }}"><a href="{{ route('admin.accreditation.division.accreditations.inactive') }}"><i class="fas fa-ban"></i> Revoked / Expired </a></li>
<li class="{{ $archivedActive ? 'current-page active' : '' }}"><a href="{{ route('admin.accreditation.division.applications.archived') }}"><i class="fas fa-archive"></i> Archived Applications </a></li>
