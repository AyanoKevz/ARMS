@php
    $isAdminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';

    // Base flags
    $routeShow = request()->routeIs('admin.accreditation.applications.show');
    $appObj = isset($application) ? $application : null;

    $appStatus = $appObj?->latestStatus?->status?->name;
    $accStatus = $appObj?->accreditation?->status;

    $isPaymentApp = in_array($appStatus, ['Awaiting Payment', 'Payment Verification']);
    $isReleasingApp = in_array($appStatus, ['Approved']);

    // Determining which menu item is active

    // 1. Dashboard
    $dashActive = request()->routeIs('admin.accreditation.dashboard');

    // 2. Profile
    $profileActive = request()->routeIs('profile.index');

    // 3. Admin List
    $adminListActive = request()->routeIs('admin.accreditation.directory.admins');

    // 4. Recommendation/Payment
    $paymentActive = request()->routeIs('admin.accreditation.applications.awaiting_payment')
        || ($routeShow && $isPaymentApp);

    // 5. Releasing (Certificate Issuance)
    $releasingActive = request()->routeIs('admin.accreditation.applications.releasing')
        || ($routeShow && $isReleasingApp && !$isPaymentApp);
@endphp

<!-- Accreditation Portal Sidebar (Verifier) -->
<li id="tour-step-dashboard" class="{{ $dashActive ? 'current-page active' : '' }}"><a href="{{ route('admin.accreditation.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard </a></li>
<li id="tour-step-profile" class="{{ $profileActive ? 'current-page active' : '' }}"><a href="{{ route('profile.index') }}"><i class="fas fa-user-circle"></i> My Profile </a></li>
<li id="tour-step-admin-list" class="{{ $adminListActive ? 'current-page active' : '' }}"><a href="{{ route('admin.accreditation.directory.admins') }}"><i class="fas fa-users-cog"></i> Admin List </a></li>

<li id="tour-step-payment" class="{{ $paymentActive ? 'current-page active' : '' }}"><a href="{{ route('admin.accreditation.applications.awaiting_payment') }}"><i class="fas fa-money-check-alt"></i> Recommendation/Payment </a></li>
<li id="tour-step-releasing" class="{{ $releasingActive ? 'current-page active' : '' }}"><a href="{{ route('admin.accreditation.applications.releasing') }}"><i class="fas fa-file-signature"></i> Certificate Issuance </a></li>
