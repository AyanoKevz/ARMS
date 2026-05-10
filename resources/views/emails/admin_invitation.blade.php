@extends('emails.layout')

@section('title', 'Admin Invitation — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #e8f0fe, #d2e3fc);
            font-size: 2.5rem;
        }
@endsection

@section('content')
    <div class="icon-circle">🔐</div>
    <h2>Admin Account Invitation</h2>
    <p>
        You have been invited to join the ARMS portal as an Administrator. To accept this invitation and activate your account, please verify that <span class="email-highlight">{{ $adminEmail }}</span> is your email address.
    </p>
    <p>Click the button below to set up your password and access the system:</p>

    <div class="btn-wrap">
        <a href="{{ $invitationUrl }}" class="btn-primary">
            Set Up Password
        </a>
    </div>

    <div class="link-fallback">
        <p>If the button above does not work, copy and paste this link into your browser:</p>
        <a href="{{ $invitationUrl }}">{{ $invitationUrl }}</a>
    </div>
@endsection
