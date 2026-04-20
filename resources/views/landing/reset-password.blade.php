@extends('layouts.landing')

@section('title', 'Reset Password | ARMS')

@section('content')
<div class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">

                <div class="login-card">

                    {{-- Logo + heading --}}
                    <img src="{{ asset('images/oshc-logo.png') }}" alt="OSHC Logo" class="login-logo">

                    <h1>Reset Password</h1>
                    <div class="gold-divider"></div>
                    <p class="login-sub">Please enter your new password below.</p>

                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">

                        {{-- Email --}}
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                name="email" value="{{ $email ?? old('email') }}" required autofocus>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                                name="password" placeholder="Min. 8 characters, letters & numbers" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Confirm Password --}}
                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password_confirmation"
                                name="password_confirmation" placeholder="Re-enter new password" required>
                        </div>

                        <button type="submit" class="btn-login mt-2">
                            <i class="bi bi-key-fill me-1"></i> Reset Password
                        </button>
                    </form>

                </div>

            </div>
        </div>
    </div>
</div>
@endsection
