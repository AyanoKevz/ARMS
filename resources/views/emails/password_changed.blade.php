@extends('emails.layout')

@section('title', 'Password Changed Successfully — ARMS')

@section('css')
        .icon-circle {
            background: linear-gradient(135deg, #e6fffa, #b2f5ea);
            font-size: 2.5rem;
        }
@endsection

@section('content')
    <div class="icon-circle">✅</div>
    <h2>Password Changed!</h2>
    <p>
        Hello <strong>{{ $user->name }}</strong>, your account password has been successfully updated.
    </p>

    <div class="details-box">
        <h3>Security Details</h3>
        <p><strong>Account:</strong> {{ $user->email }}</p>
        <p><strong>Action:</strong> Password Change</p>
        <p><strong>Status:</strong> Completed Successfully</p>
    </div>

    <p>
        You can now log in to your account using your new password.
    </p>

    <div class="btn-wrap">
        <a href="{{ route('login') }}" class="btn-primary">
            Login to ARMS
        </a>
    </div>
    
    <p style="font-size: 0.85rem; color: #777;">
        If you did not make this change, please contact our support team immediately or reset your password again to secure your account.
    </p>
@endsection
