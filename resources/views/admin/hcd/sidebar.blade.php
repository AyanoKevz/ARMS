@php
    $isAdminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';
    $isVerifier = strtolower($isAdminRole) === 'verifier';
    $isEvaluator = strtolower($isAdminRole) === 'evaluator';

    // Base flags
    $routeShow = request()->routeIs('admin.hcd.applications.show');
    $appObj = isset($application) ? $application : null;

    $appStatus = $appObj?->latestStatus?->status?->name;
    $accStatus = $appObj?->accreditation?->status;

    // Check where this application belongs:
    $isArchivedApp = $appStatus === 'Archived';
    $isActiveFatProApp = ($accStatus === 'active');
    $isInactiveFatProApp = in_array($accStatus, ['revoked', 'expired']);
    $isPaymentApp = in_array($appStatus, ['Awaiting Payment', 'Payment Verification']);
    $isInterviewApp = ($appStatus === 'Scheduled for Interview');
    
    // Renewal vs New Application classification
    $isRenewalType = $appObj && in_array($appObj->application_type, ['renewal', 'reinstatement']);
    $isNewType = $appObj && $appObj->application_type === 'new';

    // Determining which menu item is active
    
    // 1. Dashboard
    $dashActive = request()->routeIs('admin.hcd.dashboard');

    // 2. Profile
    $profileActive = request()->routeIs('profile.index');

    // 3. Admin List
    $adminListActive = request()->routeIs('admin.hcd.directory.admins');

    // 4. New Applications
    $newPendingActive = request()->routeIs('admin.hcd.applications.pending');
    $newUnderReviewActive = request()->routeIs('admin.hcd.applications.under_review') || 
        ($routeShow && $isNewType && !$isArchivedApp && !$isActiveFatProApp && !$isInactiveFatProApp && !$isPaymentApp && !$isInterviewApp);
    $newAppsParentActive = $newPendingActive || $newUnderReviewActive;

    // 5. Renewal / Reinstatement
    $renewalPendingActive = request()->routeIs('admin.hcd.renewal.pending');
    $renewalUnderReviewActive = request()->routeIs('admin.hcd.renewal.under_review') || 
        ($routeShow && $isRenewalType && !$isArchivedApp && !$isActiveFatProApp && !$isInactiveFatProApp && !$isPaymentApp && !$isInterviewApp);
    $renewalParentActive = $renewalPendingActive || $renewalUnderReviewActive;

    // 6. Schedule Interviews
    $intPendingActive = request()->routeIs('admin.hcd.interviews.pending') || 
        ($routeShow && $isInterviewApp && !$appObj?->interview);
    $intScheduledActive = request()->routeIs('admin.hcd.interviews.scheduled') || 
        ($routeShow && $isInterviewApp && $appObj?->interview);
    $interviewsParentActive = $intPendingActive || $intScheduledActive;

    // 7. Recommendation/Payment
    $paymentActive = request()->routeIs('admin.hcd.applications.awaiting_payment') || ($routeShow && $isPaymentApp);

    // 8. Releasing
    $releasingActive = request()->routeIs('admin.hcd.applications.releasing');

    // 9. Active Fatpro
    $activeFatProActive = request()->routeIs('admin.hcd.directory.fatpros') || ($routeShow && $isActiveFatProApp);

    // 10. Revoked / Expired
    $inactiveFatProActive = request()->routeIs('admin.hcd.directory.fatpros.inactive') || ($routeShow && $isInactiveFatProApp);

    // 11. Archived
    $archivedActive = request()->routeIs('admin.hcd.applications.archived') || ($routeShow && $isArchivedApp);
@endphp

<!-- HCD Admin Sidebar -->
<li id="tour-step-dashboard" class="{{ $dashActive ? 'current-page active' : '' }}"><a href="{{ route('admin.hcd.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard </a></li>
<li id="tour-step-profile" class="{{ $profileActive ? 'current-page active' : '' }}"><a href="{{ route('profile.index') }}"><i class="fas fa-user-circle"></i> My Profile </a></li>
<li id="tour-step-admin-list" class="{{ $adminListActive ? 'current-page active' : '' }}"><a href="{{ route('admin.hcd.directory.admins') }}"><i class="fas fa-users-cog"></i> HCD Admin List </a></li>

@if(!$isVerifier)
<li id="tour-step-new-apps" class="{{ $newAppsParentActive ? 'active' : '' }}"><a><i class="fas fa-folder-plus"></i>New Applications <span class="fas fa-chevron-down"></span></a>
    <ul class="nav child_menu" style="{{ $newAppsParentActive ? 'display: block;' : '' }}">
        <li class="{{ $newPendingActive ? 'current-page' : '' }}"><a href="{{ route('admin.hcd.applications.pending') }}"><i class="fas fa-hourglass-half"></i> Pending</a></li>
        <li class="{{ $newUnderReviewActive ? 'current-page' : '' }}"><a href="{{ route('admin.hcd.applications.under_review') }}"><i class="fas fa-search"></i> Under Review</a></li>
    </ul>
</li>

<li id="tour-step-renewal" class="{{ $renewalParentActive ? 'active' : '' }}"><a><i class="fas fa-sync-alt"></i> Renewal / Reinstatement <span class="fas fa-chevron-down"></span></a>
    <ul class="nav child_menu" style="{{ $renewalParentActive ? 'display: block;' : '' }}">
        <li class="{{ $renewalPendingActive ? 'current-page' : '' }}"><a href="{{ route('admin.hcd.renewal.pending') }}"><i class="fas fa-hourglass-half"></i> Pending</a></li>
        <li class="{{ $renewalUnderReviewActive ? 'current-page' : '' }}"><a href="{{ route('admin.hcd.renewal.under_review') }}"><i class="fas fa-search"></i> Under Review</a></li>
    </ul>
</li>

<li id="tour-step-interviews" class="{{ $interviewsParentActive ? 'active' : '' }}"><a><i class="fas fa-calendar-check"></i> Schedule Interviews <span class="fas fa-chevron-down"></span></a>
    <ul class="nav child_menu" style="{{ $interviewsParentActive ? 'display: block;' : '' }}">
        <li class="{{ $intPendingActive ? 'current-page' : '' }}"><a href="{{ route('admin.hcd.interviews.pending') }}"><i class="fas fa-clock"></i> Pending to Schedule</a></li>
        <li class="{{ $intScheduledActive ? 'current-page' : '' }}"><a href="{{ route('admin.hcd.interviews.scheduled') }}"><i class="fas fa-calendar-check"></i> Scheduled Interviews</a></li>
    </ul>
</li>
@endif

@if($isVerifier)
<li id="tour-step-payment" class="{{ $paymentActive ? 'current-page active' : '' }}"><a href="{{ route('admin.hcd.applications.awaiting_payment') }}"><i class="fas fa-money-check-alt"></i> Recommendation/Payment </a></li>
<li id="tour-step-releasing" class="{{ $releasingActive ? 'current-page active' : '' }}"><a href="{{ route('admin.hcd.applications.releasing') }}"><i class="fas fa-file-signature"></i> Releasing </a></li>
@elseif($isEvaluator)
<li id="tour-step-payment" class="{{ $paymentActive ? 'current-page active' : '' }}"><a href="{{ route('admin.hcd.applications.awaiting_payment') }}"><i class="fas fa-money-check-alt"></i> For Recommendation </a></li>
@endif
<li id="tour-step-active-fatpro" class="{{ $activeFatProActive ? 'current-page active' : '' }}"><a href="{{ route('admin.hcd.directory.fatpros') }}"><i class="fas fa-certificate"></i> Active FatPro </a></li>
<li id="tour-step-inactive-fatpro" class="{{ $inactiveFatProActive ? 'current-page active' : '' }}"><a href="{{ route('admin.hcd.directory.fatpros.inactive') }}"><i class="fas fa-ban"></i> Revoked / Expired </a></li>
<li id="tour-step-archived" class="{{ $archivedActive ? 'current-page active' : '' }}"><a href="{{ route('admin.hcd.applications.archived') }}"><i class="fas fa-archive"></i> Archived Applications </a></li>