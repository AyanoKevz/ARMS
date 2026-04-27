@extends('layouts.landing')

@section('title', 'Setup Admin Password | ARMS')

@section('content')
<div class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">

                <div class="login-card">

                    {{-- Logo + heading --}}
                    <img src="{{ asset('images/oshc-logo.png') }}" alt="OSHC Logo" class="login-logo">

                    <h1 style="font-size: 1.5rem;">Setup Password</h1>
                    <div class="gold-divider"></div>
                    <p class="login-sub">Welcome, {{ $pendingAdmin->first_name }}! Please set a password to activate your admin account.</p>

                    @if ($errors->any())
                        <div class="alert alert-danger pt-2 pb-2 mb-3">
                            <ul class="mb-0 list-unstyled">
                                @foreach ($errors->all() as $error)
                                    <li><small>{{ $error }}</small></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form id="setupPasswordForm" method="POST" action="{{ route('admin.setup_password.store', ['token' => $token]) }}" novalidate>
                        @csrf
                        
                        {{-- Email Display (Disabled) --}}
                        <div class="mb-3">
                            <label for="display_email">Email Address</label>
                            <input type="email" class="form-control" id="display_email" value="{{ $pendingAdmin->email }}" disabled>
                        </div>

                        {{-- Password --}}
                        <div class="mb-3">
                            <label for="password">New Password</label>
                            <div class="pw-wrap">
                                <input type="password" class="form-control" id="password"
                                    name="password" placeholder="Min. 8 chars, letters & numbers" required>
                                <button type="button" class="pw-toggle" id="togglePass"
                                    aria-label="Show/hide password">
                                    <i class="bi bi-eye" id="togglePassIcon"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Password is required.</div>
                        </div>

                        {{-- Confirm Password --}}
                        <div class="mb-4">
                            <label for="password_confirmation">Confirm Password</label>
                            <div class="pw-wrap">
                                <input type="password" class="form-control" id="password_confirmation"
                                    name="password_confirmation" placeholder="Re-type password" required>
                                <button type="button" class="pw-toggle" id="togglePassConfirm"
                                    aria-label="Show/hide password">
                                    <i class="bi bi-eye" id="togglePassConfirmIcon"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Please confirm your password.</div>
                        </div>

                        <button type="submit" class="btn-login" style="margin-top: 0;">
                            <i class="bi bi-check-circle me-1"></i> Activate Account
                        </button>

                    </form>

                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Toggle new password
        const togglePassBtn = document.getElementById('togglePass');
        const passInput = document.getElementById('password');
        const passIcon = document.getElementById('togglePassIcon');

        if (togglePassBtn) {
            togglePassBtn.addEventListener('click', function() {
                if (passInput.type === 'password') {
                    passInput.type = 'text';
                    passIcon.classList.remove('bi-eye');
                    passIcon.classList.add('bi-eye-slash');
                } else {
                    passInput.type = 'password';
                    passIcon.classList.remove('bi-eye-slash');
                    passIcon.classList.add('bi-eye');
                }
            });
        }

        // Toggle confirm password
        const toggleConfirmBtn = document.getElementById('togglePassConfirm');
        const confirmInput = document.getElementById('password_confirmation');
        const confirmIcon = document.getElementById('togglePassConfirmIcon');

        if (toggleConfirmBtn) {
            toggleConfirmBtn.addEventListener('click', function() {
                if (confirmInput.type === 'password') {
                    confirmInput.type = 'text';
                    confirmIcon.classList.remove('bi-eye');
                    confirmIcon.classList.add('bi-eye-slash');
                } else {
                    confirmInput.type = 'password';
                    confirmIcon.classList.remove('bi-eye-slash');
                    confirmIcon.classList.add('bi-eye');
                }
            });
        }
    });
</script>
@endpush
