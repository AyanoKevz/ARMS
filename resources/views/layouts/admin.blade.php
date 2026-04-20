@extends('layouts.portal')

@php
    $division = strtolower(auth()->user()?->adminProfile?->division?->name ?? 'hcd');
    
    // Division specific colors
    $divisionColors = [
        'hcd' => '#2A3F54', // Dark Blue-Grey defaults
        'scd' => '#1A5276', // Deep Blue
        'ecd' => '#117A65', // Dark Teal
        'tpid' => '#900C3F' // Dark Red
    ];
    $bgColor = $divisionColors[$division] ?? '#2A3F54';
@endphp

@push('styles')
<style>
    /* Customize the sidebar and nav header color */
    .left_col, .nav_title { 
        background-color: {{ $bgColor }} !important; 
    }
    
    .sidebar-footer a { 
        background-color: {{ $bgColor }} !important; 
        filter: brightness(90%);
    }
    .sidebar-footer a:hover { 
        background-color: {{ $bgColor }} !important; 
        filter: brightness(110%);
    }
</style>
@endpush

@section('sidebar')
    {{-- Dynamically load the sidebar based on admin division --}}
    @includeIf("partials.portal.sidebars.admin-{$division}")
@endsection
