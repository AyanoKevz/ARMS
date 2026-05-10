@extends('emails.layout')

@section('title', 'Reset Your Password — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #e8f0fe, #d2e3fc);
            font-size: 2.5rem;
        }
@endsection

@section('content')
    <div class="icon-circle">🔒</div>
    <h2>Reset Your Password</h2>
    <p>
        You are receiving this email because we received a password reset request for your account.
    </p>
    <p>Click the button below to choose a new password:</p>

    <div class="btn-wrap">
        <a href="{{ $resetUrl }}" class="btn-primary">
            Reset Password
        </a>
    </div>
    
    <p style="font-size: 0.85rem; color: #777;">
        If you did not request a password reset, no further action is required.
    </p>

    <div class="link-fallback">
        <p>If the button above does not work, copy and paste this link into your browser:</p>
        <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
    </div>
@endsection
