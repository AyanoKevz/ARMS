@extends('layouts.landing')

@section('title', 'Sign In | ARMS')



@section('content')
<div class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">

                <div class="login-card">

                    {{-- Logo + heading --}}
                    <img src="{{ asset('images/oshc-logo.png') }}" alt="OSHC Logo" class="login-logo">

                    <h1>Sign In</h1>
                    <div class="gold-divider"></div>
                    <p class="login-sub">OSHC Accreditation Reporting and Monitoring System</p>

                    @if ($errors->any())
                        <div class="alert alert-danger pt-2 pb-2 mb-3">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li><small>{{ $error }}</small></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form id="loginForm" method="POST" action="{{ route('login.post') }}" novalidate>
                        @csrf
                        {{-- Email --}}
                        <div class="mb-2">
                            <label for="login_email">Email Address</label>
                            <input type="email" class="form-control" id="login_email"
                                name="email" placeholder="you@email.com" required>
                            <div class="invalid-feedback">Please enter a valid email.</div>
                        </div>

                        {{-- Password --}}
                        <div class="mb-0">
                            <label for="login_password">Password</label>
                            <div class="pw-wrap">
                                <input type="password" class="form-control" id="login_password"
                                    name="password" placeholder="Your password" required>
                                <button type="button" class="pw-toggle" id="toggleLoginPass"
                                    aria-label="Show/hide password">
                                    <i class="bi bi-eye" id="toggleLoginPassIcon"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Password is required.</div>
                        </div>

                        <span class="forgot-link">
                            <a href="{{ route('password.request') }}">Forgot password?</a>
                        </span>

                        <button type="submit" class="btn-login">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                        </button>

                    </form>


                </div>

            </div>
        </div>
    </div>
</div>
@endsection