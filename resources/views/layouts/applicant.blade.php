@extends('layouts.portal')

@section('sidebar_subheading')
    Portal
@endsection

@push('styles')
@endpush

@section('sidebar')
    @include('applicant.sidebar')
@endsection

@push('tour')
    @include('partials.sidebar_tour', ['tourType' => 'applicant'])
@endpush
