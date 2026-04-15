@extends('layouts.landing')

@section('title', 'Register | ARMS')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/register.css') }}">
@endpush

@section('content')
<div class="register-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">

                <div class="reg-card">

                    {{-- ── Card Header ── --}}
                    <div class="reg-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:48px;height:48px;background:rgba(255,255,255,.12);border-radius:12px;
                                        display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:var(--gold-light);">
                                <i class="bi bi-person-plus-fill"></i>
                            </div>
                            <div>
                                <h1>Create an Account</h1>
                                <p>OSHC Accreditation Reporting and Monitoring System</p>
                            </div>
                        </div>
                    </div>

                    {{-- ── Card Body / Form ── --}}
                    <div class="reg-card-body">

                        <form id="registerForm" novalidate>
                            @csrf

                            {{-- ════════ STEP 1 — Accreditation Type ════════ --}}
                            <p class="form-section-title mt-0">Step 1 — Accreditation Type</p>

                            <div class="row g-3 align-items-end mb-1">

                                <div class="col-12">
                                    <label for="accreditation_type" class="form-label fw-semibold">
                                        Accreditation Type <span class="text-danger">*</span>
                                    </label>
                                    <select id="accreditation_type" name="accreditation_type_id"
                                            class="form-select" required>
                                        <option value="" disabled selected>— Select Accreditation Type —</option>
                                        {{--
                                            Seeder order (IDs):
                                            1  Practitioners              → Individual
                                            2  Consultant                 → Individual
                                            3  WEM Providers              → Organization (disabled)
                                            4  CHETO                      → Organization (disabled)
                                            5  Safety Training Orgs       → Organization (disabled)
                                            6  Safety Consultancy Orgs    → Organization (disabled)
                                            7  First Aid Training Providers → Organization (OPEN)
                                        --}}
                                        <option value="1" disabled>Practitioners</option>
                                        <option value="2" disabled>Consultant</option>
                                        <option value="3" disabled>Work and Environment Measurement Providers</option>
                                        <option value="4" disabled>Construction Heavy Equipment Testing Organizations</option>
                                        <option value="5" disabled>Safety Training Organizations</option>
                                        <option value="6" disabled>Safety Consultancy Organizations</option>
                                        <option value="7">First Aid Training Providers</option>
                                    </select>
                                    <div class="invalid-feedback">Please select an accreditation type.</div>
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Currently open: <strong>First Aid Training Providers</strong> only.
                                    </div>
                                </div>

                                <input type="hidden" id="profile_type" name="profile_type" value="">

                            </div>

                            {{-- ════════ Remaining steps (revealed on type selection) ════════ --}}
                            <div id="formSections" class="d-none">

                                {{-- STEP 2 — Account Credentials --}}
                                <p class="form-section-title">Step 2 — Account Credentials</p>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="email" class="form-label fw-semibold">
                                            Email Address <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email"
                                               placeholder="you@email.com" required>
                                        <div class="invalid-feedback">Please enter a valid email address.</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="password" class="form-label fw-semibold">
                                            Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password"
                                                   name="password" placeholder="Min. 8 characters"
                                                   minlength="8" required>
                                            <button class="btn btn-outline-secondary" type="button"
                                                    id="toggleRegPass" title="Show/hide password">
                                                <i class="bi bi-eye" id="toggleRegPassIcon"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">Password must be at least 8 characters.</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="password_confirmation" class="form-label fw-semibold">
                                            Confirm Password <span class="text-danger">*</span>
                                        </label>
                                        <input type="password" class="form-control" id="password_confirmation"
                                               name="password_confirmation" placeholder="Re-enter password" required>
                                        <div class="invalid-feedback">Passwords do not match.</div>
                                    </div>
                                </div>

                                {{-- ════════ INDIVIDUAL fields ════════ --}}
                                <div id="individualFields" class="d-none">
                                    <p class="form-section-title">Step 3 — Personal Information</p>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="first_name" class="form-label fw-semibold">
                                                First Name <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="first_name"
                                                   name="first_name" placeholder="Juan">
                                            <div class="invalid-feedback">First name is required.</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="middle_name" class="form-label fw-semibold">Middle Name</label>
                                            <input type="text" class="form-control" id="middle_name"
                                                   name="middle_name" placeholder="(optional)">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="last_name" class="form-label fw-semibold">
                                                Last Name <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="last_name"
                                                   name="last_name" placeholder="Dela Cruz">
                                            <div class="invalid-feedback">Last name is required.</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="sex" class="form-label fw-semibold">
                                                Sex <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" id="sex" name="sex">
                                                <option value="" disabled selected>— Select —</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                            <div class="invalid-feedback">Please select your sex.</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="birthday" class="form-label fw-semibold">
                                                Date of Birth <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" class="form-control" id="birthday" name="birthday">
                                            <div class="invalid-feedback">Date of birth is required.</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="region_ind" class="form-label fw-semibold">
                                                Region <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="region_ind"
                                                   name="region" placeholder="e.g. NCR">
                                            <div class="invalid-feedback">Region is required.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="city_ind" class="form-label fw-semibold">
                                                City / Municipality <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="city_ind"
                                                   name="city" placeholder="Quezon City">
                                            <div class="invalid-feedback">City is required.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="address_ind" class="form-label fw-semibold">
                                                Full Address <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="address_ind"
                                                   name="address" placeholder="Street, Barangay">
                                            <div class="invalid-feedback">Address is required.</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- ════════ ORGANIZATION fields ════════ --}}
                                <div id="organizationFields" class="d-none">
                                    <p class="form-section-title">Step 3 — Organization Information</p>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="org_name" class="form-label fw-semibold">
                                                Organization Name <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="org_name"
                                                   name="org_name" placeholder="e.g. ABC First Aid Training Center">
                                            <div class="invalid-feedback">Organization name is required.</div>
                                        </div>
                                        <div class="col-12">
                                            <label for="org_address" class="form-label fw-semibold">
                                                Business Address <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="org_address"
                                                   name="org_address" placeholder="Complete business address">
                                            <div class="invalid-feedback">Business address is required.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="head_name" class="form-label fw-semibold">
                                                Name of Head / Director <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="head_name"
                                                   name="head_name" placeholder="Full name">
                                            <div class="invalid-feedback">Head name is required.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="designation" class="form-label fw-semibold">
                                                Designation / Position <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="designation"
                                                   name="designation" placeholder="e.g. Executive Director">
                                            <div class="invalid-feedback">Designation is required.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="org_email" class="form-label fw-semibold">
                                                Organization Email <span class="text-danger">*</span>
                                            </label>
                                            <input type="email" class="form-control" id="org_email"
                                                   name="org_email" placeholder="org@email.com">
                                            <div class="invalid-feedback">A valid organization email is required.</div>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="telephone" class="form-label fw-semibold">Telephone</label>
                                            <input type="text" class="form-control" id="telephone"
                                                   name="telephone" placeholder="02-123-4567">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="fax" class="form-label fw-semibold">Fax</label>
                                            <input type="text" class="form-control" id="fax"
                                                   name="fax" placeholder="02-123-4567">
                                        </div>
                                    </div>

                                    <p class="form-section-title">Step 4 — Authorized Representative</p>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="rep_name" class="form-label fw-semibold">
                                                Representative Full Name <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="rep_name"
                                                   name="rep_full_name" placeholder="Full name">
                                            <div class="invalid-feedback">Representative name is required.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="rep_position" class="form-label fw-semibold">
                                                Position <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="rep_position"
                                                   name="rep_position" placeholder="e.g. Operations Manager">
                                            <div class="invalid-feedback">Representative position is required.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="rep_contact" class="form-label fw-semibold">
                                                Contact Number <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="rep_contact"
                                                   name="rep_contact_number" placeholder="09171234567"
                                                   pattern="^(09|\+639)\d{9}$">
                                            <div class="invalid-feedback">Enter a valid PH mobile number (e.g. 09171234567).</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="rep_email" class="form-label fw-semibold">
                                                Representative Email <span class="text-danger">*</span>
                                            </label>
                                            <input type="email" class="form-control" id="rep_email"
                                                   name="rep_email" placeholder="rep@email.com">
                                            <div class="invalid-feedback">A valid email is required.</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Submit --}}
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg fw-semibold"
                                            style="background:var(--blue-deep);border-color:var(--blue-deep);
                                                   border-radius:10px;padding:.85rem;">
                                        <i class="bi bi-check2-circle me-2"></i> Submit Registration
                                    </button>
                                </div>

                            </div>{{-- /#formSections --}}

                        </form>

                        <div class="reg-login-link">
                            Already have an account?
                            <a href="{{ route('login') }}">Sign in here</a>
                        </div>

                    </div>{{-- /.reg-card-body --}}
                </div>{{-- /.reg-card --}}

            </div>
        </div>
    </div>
</div>
@endsection
