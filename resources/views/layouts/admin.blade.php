@extends('layouts.portal')

@php
    $division = strtolower(auth()->user()?->adminProfile?->division?->name ?? 'hcd');
    $isAdminRoleName = strtolower(auth()->user()?->adminProfile?->adminRole?->name ?? '');
    $isVerifierLayout = ($isAdminRoleName === 'verifier');
@endphp

@section('sidebar_subheading')
    {{ strtoupper($division) }} Portal
@endsection

@push('styles')
@endpush

@section('sidebar')
    {{-- Dynamically load the sidebar based on admin division --}}
    @includeIf("admin.{$division}.sidebar")
@endsection

@push('tour')
    @php $tourType = $isVerifierLayout ? 'verifier' : 'evaluator'; @endphp
    @include('partials.sidebar_tour', ['tourType' => $tourType])
@endpush

