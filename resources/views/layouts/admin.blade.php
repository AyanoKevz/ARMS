@extends('layouts.portal')

@php
    $division = strtolower(auth()->user()?->adminProfile?->division?->name ?? 'hcd');

    // Sidebar subheading label: Accreditation Division → "Accreditation Portal"
    $divisionLabel = $division === 'accreditation' ? 'Accreditation' : strtoupper($division);
@endphp

@section('sidebar_subheading')
    {{ $divisionLabel }} Portal
@endsection

@push('styles')
@endpush

@section('sidebar')
    {{-- Dynamically load the sidebar based on admin division --}}
    @includeIf("admin.{$division}.sidebar")
@endsection


