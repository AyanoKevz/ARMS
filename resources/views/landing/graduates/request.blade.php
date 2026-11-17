@extends('layouts.landing')

@section('title', 'Graduate Lookup | ARMS')

@section('content')
<div class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">

                <div class="login-card">

                    <img src="{{ asset('images/oshc-logo.png') }}" alt="OSHC Logo" class="login-logo">

                    <h1>Graduate Lookup</h1>
                    <div class="gold-divider"></div>

                    @if (session('sent_to'))
                        {{-- Sent: nothing to fill in until they open the email. --}}
                        <div class="alert alert-success mb-4" style="background:rgba(25,135,84,.1);border:1px solid rgba(25,135,84,.3);border-radius:10px;color:#0f5132;font-size:.88rem;text-align:center;">
                            <i class="bi bi-envelope-check-fill me-2"></i>
                            We sent a verification link to <strong>{{ session('sent_to') }}</strong>.
                            Open it to verify your email and search the list of graduates.
                            The link is valid for {{ \App\Models\GraduateVisitor::LINK_VALID_HOURS }} hours.
                        </div>

                        <div class="reg-link mt-2">
                            Didn't receive it? Check your spam folder or
                            <a href="{{ route('graduates.request') }}">request a new link</a>.
                        </div>
                    @else
                        <p class="login-sub">
                            Search the graduates of trainings conducted by OSHC-accredited training providers.
                            Tell us who you are, and we will email you a link to the lookup.
                        </p>

                        @if (session('error'))
                        <div class="alert alert-danger mb-4" style="border-radius:10px;font-size:.88rem;text-align:center;">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                        </div>
                        @endif

                        <form id="graduateRequestForm" method="POST" action="{{ route('graduates.request.store') }}" novalidate>
                            @csrf

                            <div class="mb-3">
                                <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                                    name="name" value="{{ old('name') }}" maxlength="150" placeholder="Juan Dela Cruz" required autofocus>
                                @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="organization" class="form-label fw-semibold">Organization / Company <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('organization') is-invalid @enderror" id="organization"
                                    name="organization" value="{{ old('organization') }}" maxlength="255" placeholder="Company or agency name" required>
                                @error('organization')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                    name="email" value="{{ old('email') }}" maxlength="255" placeholder="you@email.com" required>
                                @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="purpose" class="form-label fw-semibold">Purpose <span class="text-danger">*</span></label>
                                <textarea class="form-control @error('purpose') is-invalid @enderror" id="purpose"
                                    name="purpose" rows="3" maxlength="500" placeholder="e.g. Verifying an applicant's first aid training" required>{{ old('purpose') }}</textarea>
                                @error('purpose')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <p class="text-muted mb-3" style="font-size:.78rem;line-height:1.5;">
                                <i class="bi bi-shield-lock me-1"></i>
                                Your details and searches are recorded and may be reviewed by OSHC.
                            </p>

                            <button type="submit" class="btn-login" id="graduateRequestBtn">
                                <span id="graduateRequestText">
                                    <i class="bi bi-envelope-fill me-1"></i> Send Verification Link
                                </span>
                                <span id="graduateRequestSpinner" class="d-none">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Sending link...
                                </span>
                            </button>
                        </form>
                    @endif

                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('graduateRequestForm')?.addEventListener('submit', function () {
    document.getElementById('graduateRequestBtn').disabled = true;
    document.getElementById('graduateRequestText').classList.add('d-none');
    document.getElementById('graduateRequestSpinner').classList.remove('d-none');
});
</script>
@endpush
