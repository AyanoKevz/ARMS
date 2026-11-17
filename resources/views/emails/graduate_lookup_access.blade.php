@extends('emails.layout')

@section('title', 'Graduate Lookup Access — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #e8f0fe, #d2e3fc);
            font-size: 2.5rem;
        }
@endsection

@section('content')
    <div class="icon-circle">🎓</div>
    <h2>Your Graduate Lookup Link</h2>
    <p>Hello {{ $visitorName }},</p>
    <p>
        We received your request to access the ARMS Graduate Lookup. Click the button below to verify
        your email and open the lookup, where you can search an accredited training provider and see the
        graduates of the trainings it conducted.
    </p>

    <div class="btn-wrap">
        <a href="{{ $accessUrl }}" class="btn-primary">
            Verify Email and Open Lookup
        </a>
    </div>

    <p>This link is valid for {{ $validHours }} hours. After that, please request a new one.</p>

    <div class="link-fallback">
        <p>If the button above does not work, copy and paste this link into your browser:</p>
        <a href="{{ $accessUrl }}">{{ $accessUrl }}</a>
    </div>
@endsection
