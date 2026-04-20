@extends('layouts.landing')

@section('title', 'Forgot Password | ARMS')

@section('content')
<div class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">

                <div class="login-card">

                    {{-- Logo + heading --}}
                    <img src="{{ asset('images/oshc-logo.png') }}" alt="OSHC Logo" class="login-logo">

                    <h1>Forgot Password</h1>
                    <div class="gold-divider"></div>
                    <p class="login-sub">Enter your email address and we will send you a link to reset your password.</p>

                    @if (session('status'))
                    <div class="alert alert-success mb-4" style="background:rgba(25,135,84,.1);border:1px solid rgba(25,135,84,.3);border-radius:10px;color:#0f5132;font-size:.88rem;text-align:center;">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ session('status') }}
                    </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf
                        {{-- Email --}}
                        <div class="mb-4">
                            <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                name="email" value="{{ old('email') }}" placeholder="you@email.com" required autofocus>
                            @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn-login mt-2">
                            <i class="bi bi-envelope-fill me-1"></i> Send Reset Link
                        </button>
                    </form>

                    <div class="reg-link mt-4">
                        Remember your password?
                        <a href="{{ route('login') }}">Sign In</a>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>
@endsection