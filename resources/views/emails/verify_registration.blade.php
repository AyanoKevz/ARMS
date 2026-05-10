@extends('emails.layout')

@section('title', 'Verify Your Email — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #e8f0fe, #d2e3fc);
            font-size: 2.5rem;
        }
@endsection

@section('content')
    <div class="icon-circle">✉️</div>
    <h2>Verify Your Email Address</h2>
    <p>
        Thank you for registering with ARMS. To complete your application submission,
        please verify that <span class="email-highlight">{{ $applicantEmail }}</span> is your email address.
    </p>
    <p>Click the button below to confirm your email and officially submit your registration:</p>

    <div class="btn-wrap">
        <a href="{{ $verificationUrl }}" class="btn-primary">
            Verify Email and Application
        </a>
    </div>

    <div class="link-fallback">
        <p>If the button above does not work, copy and paste this link into your browser:</p>
        <a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a>
    </div>
@endsection