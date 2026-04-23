@extends('layouts.portal')

@php
    $division = strtolower(auth()->user()?->adminProfile?->division?->name ?? 'hcd');
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


