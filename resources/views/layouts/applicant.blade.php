@extends('layouts.portal')

@section('sidebar_subheading')
    Portal
@endsection

@push('styles')
@endpush

@section('sidebar')
    {{-- FATPro and the OSH Practitioner portal have different sidebars. --}}
    @if(auth()->user()?->usesPractitionerPortal())
        @include('applicant.practitioner.sidebar')
    @else
        @include('applicant.sidebar')
    @endif
@endsection

