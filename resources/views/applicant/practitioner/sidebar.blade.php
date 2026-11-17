{{--
    OSH Practitioner / OSH Consultant portal sidebar. Unlike FATPro's
    (applicant/sidebar) there is no Submission report or Instructor section.
--}}
@php
    $dashActive    = request()->routeIs('applicant.dashboard');
    $profileActive = request()->routeIs('profile.index');
    $renewalActive = request()->routeIs('applicant.practitioner.renewal');
@endphp

@push('styles')
<link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/applicant-practitioner.css') }}">
@endpush

<li class="{{ $dashActive ? 'current-page active' : '' }}"><a href="{{ route('applicant.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard </a></li>
<li class="{{ $profileActive ? 'current-page active' : '' }}"><a href="{{ route('profile.index') }}"><i class="fas fa-user-circle"></i> My Profile </a></li>
<li class="{{ $renewalActive ? 'current-page active' : '' }}"><a href="{{ route('applicant.practitioner.renewal') }}"><i class="fas fa-redo-alt"></i> Renewal </a></li>
