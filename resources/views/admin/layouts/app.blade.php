@extends('layouts.gentelella')

@section('sidebar')
    {{-- Dynamically load the sidebar based on admin role. For now, defaulting to HCD --}}
    @include('admin.partials.sidebars.hcd')

@endsection
