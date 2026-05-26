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

                    <form id="resetPasswordForm" method="POST" action="{{ route('password.update') }}" novalidate>
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
                            <div class="pw-wrap">
                                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                                    name="password" placeholder="Min. 8 characters, letters & numbers" required>
                                <button type="button" class="pw-toggle" id="toggleResetPass"
                                    aria-label="Show/hide password">
                                    <i class="bi bi-eye" id="toggleResetPassIcon"></i>
                                </button>
                            </div>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Confirm Password --}}
                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <div class="pw-wrap">
                                <input type="password" class="form-control" id="password_confirmation"
                                    name="password_confirmation" placeholder="Re-enter new password" required>
                                <button type="button" class="pw-toggle" id="toggleResetPassConfirm"
                                    aria-label="Show/hide password">
                                    <i class="bi bi-eye" id="toggleResetPassConfirmIcon"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback" id="confirmPasswordFeedback">Passwords do not match.</div>
                        </div>

                        <button type="submit" class="btn-login mt-2" id="resetSubmitBtn">
                            <span id="resetSubmitText">
                                <i class="bi bi-key-fill me-1"></i> Reset Password
                            </span>
                            <span id="resetSubmitSpinner" class="d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Resetting...
                            </span>
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
        const form = document.getElementById('resetPasswordForm');
        const passInput = document.getElementById('password');
        const confirmInput = document.getElementById('password_confirmation');
        const confirmFeedback = document.getElementById('confirmPasswordFeedback');

        // New Password Toggle
        const toggleBtn = document.getElementById('toggleResetPass');
        const toggleIcon = document.getElementById('toggleResetPassIcon');
        if (toggleBtn && toggleIcon && passInput) {
            toggleBtn.addEventListener('click', function () {
                const isText = passInput.type === 'text';
                passInput.type = isText ? 'password' : 'text';
                toggleIcon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
        }

        // Confirm Password Toggle
        const toggleConfirmBtn = document.getElementById('toggleResetPassConfirm');
        const toggleConfirmIcon = document.getElementById('toggleResetPassConfirmIcon');
        if (toggleConfirmBtn && toggleConfirmIcon && confirmInput) {
            toggleConfirmBtn.addEventListener('click', function () {
                const isText = confirmInput.type === 'text';
                confirmInput.type = isText ? 'password' : 'text';
                toggleConfirmIcon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
        }

        function validatePasswordMatch() {
            if (confirmInput.value) {
                const matches = confirmInput.value === passInput.value;
                confirmInput.setCustomValidity(matches ? '' : 'Passwords do not match.');
                if (matches) {
                    confirmInput.classList.remove('is-invalid');
                    confirmInput.classList.add('is-valid');
                } else {
                    confirmInput.classList.remove('is-valid');
                    confirmInput.classList.add('is-invalid');
                }
            } else {
                confirmInput.setCustomValidity('');
                confirmInput.classList.remove('is-invalid', 'is-valid');
            }
        }

        if (passInput && confirmInput) {
            passInput.addEventListener('input', validatePasswordMatch);
            confirmInput.addEventListener('input', validatePasswordMatch);
        }

        if (form) {
            form.addEventListener('submit', function(e) {
                validatePasswordMatch();
                if (!this.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    const firstInvalid = this.querySelector(':invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                } else {
                    this.classList.add('was-validated');
                    const btn = document.getElementById('resetSubmitBtn');
                    const text = document.getElementById('resetSubmitText');
                    const spinner = document.getElementById('resetSubmitSpinner');
                    if(btn) btn.disabled = true;
                    if(text) text.classList.add('d-none');
                    if(spinner) spinner.classList.remove('d-none');
                }
            });
        }
    });
</script>
@endpush
