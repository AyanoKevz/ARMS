@extends('layouts.landing')

@section('title', 'Register | ARMS')

@section('content')
<div class="register-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9 mx-auto" style="max-width: 900px;">

                <div class="reg-card">

                    {{-- Card Header --}}
                    <div class="reg-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:48px;height:48px;background:rgba(255,255,255,.12);border-radius:12px;
                                        display:flex;align-items:center;justify-content:center;">
                                <img src="{{ asset('images/oshc-icon.ico') }}" alt="Icon" style="width: 28px; height: 28px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
                            </div>
                            <div>
                                <h1>Create an Account</h1>
                                <p>OSHC Accreditation Reporting and Monitoring System</p>
                            </div>
                        </div>
                    </div>

                    {{-- Card Body / Form --}}
                    <div class="reg-card-body">

                        {{-- Dynamic Alert --}}
                        <div id="dynamicAlert" class="alert d-none alert-dismissible fade show" role="alert" style="text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 1.5rem;">
                            <span id="dynamicAlertMessage"></span>
                            <button type="button" class="btn-close" aria-label="Close" onclick="document.getElementById('dynamicAlert').classList.add('d-none')"></button>
                        </div>

                        <div id="emailSentPanel" class="d-none text-center py-4">
                            <div style="font-size:3.5rem;margin-bottom:1rem;animation:pulse 2s ease-in-out infinite;color:var(--navy-deep);"><i class="bi bi-envelope-paper"></i></div>
                            <h2 style="color:var(--navy-deep);font-size:1.4rem;margin-bottom:.5rem;">Check Your Email</h2>
                            <div style="width:40px;height:3px;background:var(--gold-light);border-radius:2px;margin:0 auto 1.25rem;"></div>
                            <p style="color:#555;font-size:.95rem;line-height:1.7;">
                                A verification link has been sent to<br>
                                <strong id="sentToEmail" style="color:var(--navy-deep);"></strong>
                            </p>
                            <div class="alert mt-3 mb-3" style="background:rgba(212,172,75,.1);border:1px solid rgba(212,172,75,.3);border-radius:10px;color:#7a5c00;font-size:.88rem;text-align:left;">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Please open the email and click <strong>"Verify Email &amp; Submit Application"</strong> to officially submit your application. The link expires in <strong>5 minutes</strong>.
                            </div>
                            <p style="font-size:.82rem;color:#999;">
                                Didn't receive it? Check your spam folder, or
                                <a href="#" id="tryAgainLink" style="color:var(--gold-light);">try again</a>.
                            </p>
                        </div>

                        {{-- â”€â”€ Registration Form â”€â”€ --}}
                        <form id="registerForm" action="{{ route('register.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                            @csrf

                            {{-- STEP 1  &mdash;  Accreditation Type --}}
                            <div id="allFormSteps">
                                <p class="form-section-title mt-0">Step 1  &mdash;  Accreditation Type</p>

                            <div class="row g-3 align-items-end mb-1">

                                <div class="col-12">
                                    <label for="accreditation_type" class="form-label fw-semibold">
                                        Accreditation Type <span class="text-danger">*</span>
                                    </label>
                                    <select id="accreditation_type" name="accreditation_type_id"
                                        class="form-select" required>
                                        <option value="" disabled selected> &mdash;  Select Accreditation Type  &mdash; </option>
                                        {{--
                                            Seeder order (IDs):
                                            1  Practitioners              â†’ Individual
                                            2  Consultant                 â†’ Individual
                                            3  WEM Providers              â†’ Organization (disabled)
                                            4  CHETO                      â†’ Organization (disabled)
                                            5  Safety Training Orgs       â†’ Organization (disabled)
                                            6  Safety Consultancy Orgs    â†’ Organization (disabled)
                                            7  First Aid Training Providers â†’ Organization (OPEN)
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

                            {{-- Remaining steps (revealed on type selection) --}}
                            <div id="formSections" class="d-none">

                                {{-- STEP 2  &mdash;  Account Credentials --}}
                                <p class="form-section-title">Step 2  &mdash;  Account Credentials</p>

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
                                                name="password" placeholder="Min. 8 characters, letters & numbers"
                                                minlength="8" required>
                                            <button class="btn btn-outline-secondary" type="button"
                                                id="toggleRegPass" title="Show/hide password">
                                                <i class="bi bi-eye" id="toggleRegPassIcon"></i>
                                            </button>
                                        </div>
                                        <div id="passwordStrengthFeedback" class="mt-2" style="font-size: 0.85rem; line-height: 1.4;">
                                            <div class="text-secondary" id="rule-length"><i class="bi bi-circle me-2"></i>At least 8 characters</div>
                                            <div class="text-secondary" id="rule-letter"><i class="bi bi-circle me-2"></i>Contains letters</div>
                                            <div class="text-secondary" id="rule-number"><i class="bi bi-circle me-2"></i>Contains numbers</div>
                                        </div>
                                        <div class="invalid-feedback">Please enter a valid password.</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="password_confirmation" class="form-label fw-semibold">
                                            Confirm Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password_confirmation"
                                                name="password_confirmation" placeholder="Re-enter password" required>
                                            <button class="btn btn-outline-secondary" type="button"
                                                id="toggleRegPassConfirm" title="Show/hide password">
                                                <i class="bi bi-eye" id="toggleRegPassConfirmIcon"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">Passwords do not match.</div>
                                    </div>
                                </div>

                                {{-- â•â•â•â•â•â•â•â• INDIVIDUAL fields â•â•â•â•â•â•â•â• --}}
                                <div id="individualFields" class="d-none">
                                    <p class="form-section-title">Step 3  &mdash;  Personal Information</p>
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
                                                <option value="" disabled selected> &mdash;  Select  &mdash; </option>
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

                                {{-- â•â•â•â•â•â•â•â• ORGANIZATION fields â•â•â•â•â•â•â•â• --}}
                                <div id="organizationFields" class="d-none">
                                    <p class="form-section-title">Step 3  &mdash;  Organization Information</p>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="org_name" class="form-label fw-semibold">
                                                Name of FATPro<span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="org_name"
                                                name="org_name" placeholder="e.g. ABC First Aid Training Center">
                                            <div class="invalid-feedback">Name is required.</div>
                                        </div>
                                        <div class="col-12">
                                            <label for="org_address" class="form-label fw-semibold">
                                                Complete Address <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="org_address"
                                                name="org_address" placeholder="Complete business address">
                                            <div class="invalid-feedback">Address is required.</div>
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
                                            <label for="telephone" class="form-label fw-semibold">Telephone Number</label>
                                            <input type="text" class="form-control" id="telephone"
                                                name="telephone" placeholder="02-123-4567">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="fax" class="form-label fw-semibold">Facsimile Number</label>
                                            <input type="text" class="form-control" id="fax"
                                                name="fax" placeholder="02-123-4567">
                                        </div>
                                        <div class="col-12">
                                            <label for="org_email" class="form-label fw-semibold">
                                                Email Address <span class="text-danger">*</span>
                                            </label>
                                            <input type="email" class="form-control" id="org_email"
                                                name="org_email" placeholder="org@email.com">
                                            <div class="invalid-feedback">Email is required.</div>
                                        </div>
                                    </div>

                                <p class="form-section-title mt-4">Step 4  &mdash;  Authorized Representative</p>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="rep_name" class="form-label fw-semibold">
                                            Representative Full Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="rep_name"
                                            name="rep_full_name" placeholder="Full name">
                                        <div class="invalid-feedback">Name is required.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="rep_position" class="form-label fw-semibold">
                                            Position <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="rep_position"
                                            name="rep_position" placeholder="e.g. Operations Manager">
                                        <div class="invalid-feedback">Position is required.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="rep_contact" class="form-label fw-semibold">
                                            Contact Number <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="rep_contact"
                                            name="rep_contact_number" placeholder="09171234567"
                                            pattern="^(09|\+639)\d{9}$" maxlength="11">
                                        <div class="invalid-feedback">Enter a valid PH mobile number (e.g. 09171234567).</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="rep_email" class="form-label fw-semibold">
                                            Representative Email <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" class="form-control" id="rep_email"
                                            name="rep_email" placeholder="rep@email.com">
                                        <div class="invalid-feedback">Email is required.</div>
                                    </div>
                                </div>
                            </div>

                            {{-- â•â•â•â•â•â•â•â• STEP 5  &mdash;  Instructors & Credentials â•â•â•â•â•â•â•â• --}}
                            <p class="form-section-title mt-4">Step 5  &mdash;  Instructors &amp; Credentials</p>
                            <div class="mb-3">
                                <div class="alert alert-info rounded-3" style="background:rgba(46,111,216,.08);border:1px solid rgba(46,111,216,.2);color:var(--blue-deep);">
                                    <h6 class="fw-bold mb-1"><i class="bi bi-person-badge-fill me-2 text-primary"></i>Instructor Credentials</h6>
                                    <p class="mb-0" style="font-size:.85rem;">
                                        Add at least <strong>one instructor</strong>. Each instructor must provide personal information and at least one credential with supporting PDF. Upload PDFs in <strong>PDF format only</strong> (max 10 MB each).
                                    </p>
                                </div>
                            </div>

                            {{-- â”€â”€ Hidden template card (cloned by JS) â”€â”€ --}}
                            <template id="instructorTemplate" class="d-none" aria-hidden="true">
                                <div class="instructor-card border rounded-3 bg-white shadow-sm p-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <h6 class="fw-bold mb-0" style="color:#0b3d91;">
                                            <i class="bi bi-person-fill me-2"></i>
                                            <span class="instructor-label">Instructor #1</span>
                                        </h6>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-instructor-btn d-none">
                                            <i class="bi bi-trash me-1"></i>Remove
                                        </button>
                                    </div>

                                    {{-- Personal Info --}}
                                    <p class="fw-semibold mb-2" style="font-size:.82rem;color:#555;text-transform:uppercase;letter-spacing:.05em;">Personal Information</p>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold" style="font-size:.88rem;">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][first_name]" placeholder="Juan" required>
                                            <div class="invalid-feedback">First name is required.</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold" style="font-size:.88rem;">Middle Name</label>
                                            <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][middle_name]" placeholder="(optional)">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold" style="font-size:.88rem;">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][last_name]" placeholder="Dela Cruz" required>
                                            <div class="invalid-feedback">Last name is required.</div>
                                        </div>
                                    </div>

                                    {{-- TESDA EMS NC II/III --}}
                                    <div class="credential-block border rounded-2 p-3 mb-2" style="background:#f8f9ff;">
                                        <p class="fw-bold mb-2" style="font-size:.83rem;color:#0b3d91;"><span class="badge me-1" style="background:#0b3d91;font-size:.7rem;">EMS</span>TESDA EMS NC II/III</p>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Certificate Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][EMS][number]" placeholder="e.g. NC-EMS-2024-00001" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Certificate number is required.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Issued Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][EMS][issued_date]" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Issued date is required.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Validity Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][EMS][validity_date]" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Validity date is required.</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF <span class="text-danger">*</span></label>
                                                <div class="file-upload-wrapper">
                                                    <input class="real-file-input visually-hidden" type="file" name="instructors[__IDX__][credentials][EMS][pdf]" id="inst_ems_pdf___IDX__" accept=".pdf" required>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label for="inst_ems_pdf___IDX__" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn"><i class="bi bi-cloud-upload me-1"></i>Choose File</label>
                                                        <span class="file-name-text text-muted" style="font-size:.78rem;">No file chosen</span>
                                                    </div>
                                                    <div class="invalid-feedback file-invalid-feedback" style="font-size:0.8rem;margin-top:4px;">Please upload the EMS certificate PDF.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- TESDA TM1 --}}
                                    <div class="credential-block border rounded-2 p-3 mb-2" style="background:#f8f9ff;">
                                        <p class="fw-bold mb-2" style="font-size:.83rem;color:#0b3d91;"><span class="badge me-1" style="background:#0b3d91;font-size:.7rem;">TM1</span>TESDA TM1</p>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Certificate Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][TM1][number]" placeholder="e.g. TM1-2024-00001" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Certificate number is required.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Issued Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][TM1][issued_date]" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Issued date is required.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Validity Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][TM1][validity_date]" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Validity date is required.</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF <span class="text-danger">*</span></label>
                                                <div class="file-upload-wrapper">
                                                    <input class="real-file-input visually-hidden" type="file" name="instructors[__IDX__][credentials][TM1][pdf]" id="inst_tm1_pdf___IDX__" accept=".pdf" required>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label for="inst_tm1_pdf___IDX__" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn"><i class="bi bi-cloud-upload me-1"></i>Choose File</label>
                                                        <span class="file-name-text text-muted" style="font-size:.78rem;">No file chosen</span>
                                                    </div>
                                                    <div class="invalid-feedback file-invalid-feedback" style="font-size:0.8rem;margin-top:4px;">Please upload the TM1 certificate PDF.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- TESDA NTTC --}}
                                    <div class="credential-block border rounded-2 p-3 mb-2" style="background:#f8f9ff;">
                                        <p class="fw-bold mb-2" style="font-size:.83rem;color:#0b3d91;"><span class="badge me-1" style="background:#0b3d91;font-size:.7rem;">NTTC</span>TESDA NTTC</p>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Certificate Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][NTTC][number]" placeholder="e.g. NTTC-2024-00001" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Certificate number is required.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Issued Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][NTTC][issued_date]" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Issued date is required.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Validity Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][NTTC][validity_date]" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Validity date is required.</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF <span class="text-danger">*</span></label>
                                                <div class="file-upload-wrapper">
                                                    <input class="real-file-input visually-hidden" type="file" name="instructors[__IDX__][credentials][NTTC][pdf]" id="inst_nttc_pdf___IDX__" accept=".pdf" required>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label for="inst_nttc_pdf___IDX__" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn"><i class="bi bi-cloud-upload me-1"></i>Choose File</label>
                                                        <span class="file-name-text text-muted" style="font-size:.78rem;">No file chosen</span>
                                                    </div>
                                                    <div class="invalid-feedback file-invalid-feedback" style="font-size:0.8rem;margin-top:4px;">Please upload the NTTC certificate PDF.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- BOSH SO1/SO2 --}}
                                    <div class="credential-block border rounded-2 p-3 mb-2" style="background:#f8f9ff;">
                                        <p class="fw-bold mb-2" style="font-size:.83rem;color:#0b3d91;"><span class="badge me-1" style="background:#0b3d91;font-size:.7rem;">BOSH</span>BOSH SO1/SO2</p>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Certificate Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][BOSH][number]" placeholder="e.g. BOSH-2024-00001" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Certificate number is required.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Validity Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][BOSH][validity_date]" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Validity date is required.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Training Date(s) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][BOSH][training_dates]" placeholder="e.g. Jan 10-12, 2024" required>
                                                <div class="invalid-feedback" style="font-size:.78rem;">Training date(s) are required.</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF <span class="text-danger">*</span></label>
                                                <div class="file-upload-wrapper">
                                                    <input class="real-file-input visually-hidden" type="file" name="instructors[__IDX__][credentials][BOSH][pdf]" id="inst_bosh_pdf___IDX__" accept=".pdf" required>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label for="inst_bosh_pdf___IDX__" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn"><i class="bi bi-cloud-upload me-1"></i>Choose File</label>
                                                        <span class="file-name-text text-muted" style="font-size:.78rem;">No file chosen</span>
                                                    </div>
                                                    <div class="invalid-feedback file-invalid-feedback" style="font-size:0.8rem;margin-top:4px;">Please upload the BOSH certificate PDF.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Service Agreement --}}
                                    <div class="credential-block border rounded-2 p-3" style="background:#fffdf4;border-color:#d4ac4b !important;">
                                        <p class="fw-bold mb-2" style="font-size:.83rem;color:#7a5c00;"><i class="bi bi-file-earmark-text-fill me-1"></i>Service Agreement</p>
                                        <div class="file-upload-wrapper">
                                            <input class="real-file-input visually-hidden" type="file" name="instructors[__IDX__][service_agreement]" id="inst_sa___IDX__" accept=".pdf" required>
                                            <div class="d-flex align-items-center gap-2">
                                                <label for="inst_sa___IDX__" class="btn btn-sm mb-0 px-3 fw-semibold custom-file-btn" style="border:1px solid #d4ac4b;color:#7a5c00;"><i class="bi bi-cloud-upload me-1"></i>Choose PDF</label>
                                                <span class="file-name-text text-muted" style="font-size:.78rem;">No file chosen</span>
                                            </div>
                                            <div class="invalid-feedback file-invalid-feedback" style="font-size:0.8rem;margin-top:4px;">Please upload the Service Agreement PDF.</div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            {{-- â”€â”€ Live instructor cards container â”€â”€ --}}
                            <div id="instructorCardsContainer"></div>

                            <div class="d-flex justify-content-end mb-4">
                                <button type="button" id="addInstructorBtn" class="btn btn-outline-primary btn-sm fw-semibold px-4" style="border-radius:8px;">
                                    <i class="bi bi-plus-circle me-1"></i>Add Instructor
                                </button>
                            </div>

                            <p class="form-section-title mt-4">Step 6  &mdash;  Submission of Required Documents</p>
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="alert alert-info rounded-3" style="background: rgba(46,111,216,.08); border: 1px solid rgba(46,111,216,.2); color: var(--blue-deep);">
                                        <h6 class="fw-bold mb-2"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Document Upload Instructions</h6>
                                        <p class="mb-0" style="font-size: 0.85rem;">
                                            Upload each required file in <strong>PDF format only</strong> (Maximum: <strong>10 MB</strong> per file).
                                            Fill in all text and date fields exactly as they appear.
                                        </p>
                                    </div>
                                </div>

                                {{-- â”€â”€ Type 1: Legal Requirements to Operate Business â”€â”€ --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">1</span>Legal Requirements to Operate Business</h6>
                                        <div class="row g-3">
                                            @foreach([
                                                ['code'=>'LEGAL_01','title'=>'DOLE Registration','label'=>'Certificate of Registration to the Department of Labor and Employment (Rule 10-20, OSHS).','required'=>true],
                                                ['code'=>'LEGAL_02','title'=>'Business Registration','label'=>'Registration of business with DTI, SEC, or CDA.','required'=>true],
                                                ['code'=>'LEGAL_03','title'=>'Articles of Incorporation','label'=>'Articles of Incorporation with By-Laws','required'=>true],
                                                ['code'=>'LEGAL_04','title'=>'Mayor\'s Permit','label'=>'Valid Mayor\'s Permit','required'=>true],
                                                ['code'=>'LEGAL_05','title'=>'BIR Registration & TIN','label'=>'Registration Certificate with BIR, TIN, receipts, and Books of Accounts','required'=>true],
                                                ['code'=>'LEGAL_06','title'=>'DOLE clearance','label'=>'DOLE-issued certificate of no pending labor standard case','required'=>true],
                                                ['code'=>'LEGAL_07','title'=>'Lease/Ownership Agreement','label'=>'Lease agreement or evidence of ownership of building','required'=>false],
                                            ] as $f)
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label fw-bold mb-0" style="font-size:.88rem;">{{ $f['title'] }} @if($f['required']) <span class="text-danger">*</span> @endif </label>
                                                <div class="form-text mt-0 mb-2" style="font-size:.75rem; line-height: 1.2; color: #6c757d;">{{ $f['label'] }}</div>
                                                <div class="file-upload-wrapper mt-1">
                                                    <input class="real-file-input visually-hidden" type="file" name="documents[{{ $f['code'] }}]" id="doc_{{ $f['code'] }}" accept=".pdf" @if($f['required'] ?? true) required @endif>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label for="doc_{{ $f['code'] }}" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                                                            <i class="bi bi-cloud-upload me-1"></i> Choose File
                                                        </label>
                                                        <span class="file-name-text text-muted text-truncate" style="font-size: .8rem; max-width: 200px;">No file chosen</span>
                                                    </div>
                                                    <div class="invalid-feedback file-invalid-feedback" style="font-size: 0.8rem; margin-top: 4px;">Please select a valid PDF file.</div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                {{-- â”€â”€ Type 2: Training Management and Staff â”€â”€ --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">2</span>Training Management and Staff</h6>
                                        <div class="row g-3">
                                            @foreach([
                                                ['code'=>'TRAIN_01','title'=>'Organizational Chart','label'=>'Chart showing management, teaching and support staff','required'=>true],
                                                ['code'=>'TRAIN_02','title'=>'TESDA Certificate','label'=>'For TVIs: EMS NC II Program Registration from TESDA (if applicable)','required'=>false],
                                                ['code'=>'TRAIN_03','title'=>'Training Monitoring','label'=>'Monitoring of delivery of training program plan','required'=>true],
                                            ] as $f)
                                            <div class="col-12 mb-2">
                                                <label class="form-label fw-bold mb-0" style="font-size:.88rem;">{{ $f['title'] }} @if($f['required']) <span class="text-danger">*</span> @endif</label>
                                                <div class="form-text mt-0 mb-2" style="font-size:.75rem; line-height: 1.2; color: #6c757d;">{{ $f['label'] }}</div>
                                                <div class="file-upload-wrapper mt-1">
                                                    <input class="real-file-input visually-hidden" type="file" name="documents[{{ $f['code'] }}]" id="doc_{{ $f['code'] }}" accept=".pdf" @if($f['required'] ?? true) required @endif>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label for="doc_{{ $f['code'] }}" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                                                            <i class="bi bi-cloud-upload me-1"></i> Choose File
                                                        </label>
                                                        <span class="file-name-text text-muted text-truncate" style="font-size: .8rem; max-width: 200px;">No file chosen</span>
                                                    </div>
                                                    <div class="invalid-feedback file-invalid-feedback" style="font-size: 0.8rem; margin-top: 4px;">Please select a valid PDF file.</div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                {{-- â”€â”€ Type 3: Premises Including Occupational Safety â”€â”€ --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">3</span>Premises Including Occupational Safety</h6>
                                        <div class="row g-3">
                                            @foreach([
                                                ['code'=>'PREM_01','title'=>'Location Map','label'=>'Organization\'s location map','required'=>true],
                                                ['code'=>'PREM_02','title'=>'Site Floor Plan','label'=>'Detailed floor plan including classrooms, facilities, and emergency exits.','required'=>true],
                                                ['code'=>'PREM_03','title'=>'OSH Policy & Program','label'=>'Occupational Safety and Health Policy and Program','required'=>true],
                                                ['code'=>'PREM_04','title'=>'Decontamination Procedures','label'=>'Written procedures for decontamination of first aid tools/equipment.','required'=>true],
                                                ['code'=>'PREM_05','title'=>'Safety Officers List','label'=>'List of qualified and designated "safety officers".','required'=>true],
                                                ['code'=>'PREM_06','title'=>'First-Aiders List','label'=>'List of qualified first-aiders in the organization.','required'=>true],
                                                ['code'=>'PREM_07','title'=>'First-Aider Certificate','label'=>'Valid first-aider certificate in your organization.','required'=>true],
                                            ] as $f)
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label fw-bold mb-0" style="font-size:.88rem;">{{ $f['title'] }} @if($f['required']) <span class="text-danger">*</span> @endif</label>
                                                <div class="form-text mt-0 mb-2" style="font-size:.75rem; line-height: 1.2; color: #6c757d;">{{ $f['label'] }}</div>
                                                <div class="file-upload-wrapper mt-1">
                                                    <input class="real-file-input visually-hidden" type="file" name="documents[{{ $f['code'] }}]" id="doc_{{ $f['code'] }}" accept=".pdf" @if($f['required'] ?? true) required @endif>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label for="doc_{{ $f['code'] }}" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                                                            <i class="bi bi-cloud-upload me-1"></i> Choose File
                                                        </label>
                                                        <span class="file-name-text text-muted text-truncate" style="font-size: .8rem; max-width: 200px;">No file chosen</span>
                                                    </div>
                                                    <div class="invalid-feedback file-invalid-feedback" style="font-size: 0.8rem; margin-top: 4px;">Please select a valid PDF file.</div>
                                                </div>
                                            </div>
                                            @endforeach
                                            {{-- 1 Date input --}}
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label fw-bold mb-0" style="font-size:.88rem;">Certificate Validity Date <span class="text-danger">*</span></label>
                                                <div class="form-text mt-0 mb-2" style="font-size:.75rem; line-height: 1.2; color: #6c757d;">Validity date of your first-aider certificate.</div>
                                                <input class="form-control form-control-sm" type="date" name="documents[PREM_DATE]" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- â”€â”€ Type 4: Policies on IP and Data Protection â”€â”€ --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">4</span>Policies on Intellectual Property and Data Protection</h6>
                                        <div class="row g-3">
                                            {{-- 1 Text --}}
                                            <div class="col-12 mb-2">
                                                <label class="form-label fw-bold mb-0" style="font-size:.88rem;">Data Protection Officer <span class="text-danger">*</span></label>
                                                <div class="form-text mt-0 mb-2" style="font-size:.75rem; line-height: 1.2; color: #6c757d;">Please provide the full name of your designated Data Protection Officer.</div>
                                                <input class="form-control form-control-sm" type="text" name="documents[IP_DPO_NAME]" placeholder="Full name of  Data Protection Officer" required>
                                            </div>
                                            {{-- 2 File --}}
                                            @foreach([
                                                ['code'=>'IP_01','title'=>'Data Privacy Policy','label'=>'Written policy on how to ensure privacy and security of the data subjects.','required'=>true],
                                                ['code'=>'IP_02','title'=>'Intellectual Property Policy','label'=>'Written policy on use of intellectual properties as applicable.','required'=>true],
                                            ] as $f)
                                            <div class="col-12 mb-2">
                                                <label class="form-label fw-bold mb-0" style="font-size:.88rem;">{{ $f['title'] }} @if($f['required']) <span class="text-danger">*</span> @endif</label>
                                                <div class="form-text mt-0 mb-2" style="font-size:.75rem; line-height: 1.2; color: #6c757d;">{{ $f['label'] }}</div>
                                                <div class="file-upload-wrapper mt-1">
                                                    <input class="real-file-input visually-hidden" type="file" name="documents[{{ $f['code'] }}]" id="doc_{{ $f['code'] }}" accept=".pdf" @if($f['required'] ?? true) required @endif>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label for="doc_{{ $f['code'] }}" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                                                            <i class="bi bi-cloud-upload me-1"></i> Choose File
                                                        </label>
                                                        <span class="file-name-text text-muted text-truncate" style="font-size: .8rem; max-width: 200px;">No file chosen</span>
                                                    </div>
                                                    <div class="invalid-feedback file-invalid-feedback" style="font-size: 0.8rem; margin-top: 4px;">Please select a valid PDF file.</div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- â”€â”€ Type 5: Quality Assurance and Enhancement â”€â”€ --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">5</span>Quality Assurance and Enhancement</h6>
                                        <div class="row g-3">
                                            @foreach([
                                                ['code'=>'QA_01','title'=>'Course Review Procedures','label'=>'Written procedures for conducting training course review, including programs and names of trainers.','required'=>false],
                                                ['code'=>'QA_02','title'=>'Test Results Summary','label'=>'Template summary of the pre- and post-test results.','required'=>true],
                                                ['code'=>'QA_03','title'=>'Evaluation Summary','label'=>'Template summary of general and individual trainer evaluation numerical ratings.','required'=>true],
                                                ['code'=>'QA_04','title'=>'Assessment Tools','label'=>'Sample assessment tools such as test questions, etc.','required'=>true],
                                                ['code'=>'QA_05','title'=>'Participant Directory Template','label'=>'Template containing participant data, unique codes, and ID pictures.','required'=>true],
                                                ['code'=>'QA_06','title'=>'Attendance Sheet Template','label'=>'Daily attendance sheet template.','required'=>true],
                                                ['code'=>'QA_07','title'=>'Emergency First Aid Manual','label'=>'Emergency First Aid (1-day) Manual.','required'=>true],
                                                ['code'=>'QA_08','title'=>'Occupational First Aid Manual','label'=>'Occupational First Aid (2-days) Manual.','required'=>true],
                                                ['code'=>'QA_09','title'=>'Standard First Aid Manual','label'=>'Standard First Aid (4-days) Manual.','required'=>true],
                                            ] as $f)
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label fw-bold mb-0" style="font-size:.88rem;">{{ $f['title'] }} @if($f['required']) <span class="text-danger">*</span> @endif</label>
                                                <div class="form-text mt-0 mb-2" style="font-size:.75rem; line-height: 1.2; color: #6c757d;">{{ $f['label'] }}</div>
                                                <div class="file-upload-wrapper mt-1">
                                                    <input class="real-file-input visually-hidden" type="file" name="documents[{{ $f['code'] }}]" id="doc_{{ $f['code'] }}" accept=".pdf" @if($f['required'] ?? true) required @endif>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label for="doc_{{ $f['code'] }}" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                                                            <i class="bi bi-cloud-upload me-1"></i> Choose File
                                                        </label>
                                                        <span class="file-name-text text-muted text-truncate" style="font-size: .8rem; max-width: 200px;">No file chosen</span>
                                                    </div>
                                                    <div class="invalid-feedback file-invalid-feedback" style="font-size: 0.8rem; margin-top: 4px;">Please select a valid PDF file.</div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                {{-- â”€â”€ Type 6: Training Equipment and Materials â”€â”€ --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">6</span>Training Equipment and Materials</h6>
                                        <div class="row g-3">
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label fw-bold mb-0" style="font-size:.88rem;">Equipment & Materials List <span class="text-danger">*</span></label>
                                                <div class="form-text mt-0 mb-2" style="font-size:.75rem; line-height: 1.2; color: #6c757d;">Unified document with photos of First-Aid materials, general equipment, and participant kits (Refer to FATPro MOP).</div>
                                                <div class="file-upload-wrapper mt-1">
                                                    <input class="real-file-input visually-hidden" type="file" name="documents[EQUIP_01]" id="doc_EQUIP_01" accept=".pdf" required>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label for="doc_EQUIP_01" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn">
                                                            <i class="bi bi-cloud-upload me-1"></i> Choose File
                                                        </label>
                                                        <span class="file-name-text text-muted text-truncate" style="font-size: .8rem; max-width: 200px;">No file chosen</span>
                                                    </div>
                                                    <div class="invalid-feedback file-invalid-feedback" style="font-size: 0.8rem; margin-top: 4px;">Please select a valid PDF file.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            </div><!-- /#allFormSteps -->

                            <div id="reviewSection" class="d-none mt-4">
                                <p class="form-section-title">Step 7  &mdash;  Review &amp; Submit</p>
                                <div class="card bg-light border-0 mb-4 p-4 rounded-3" style="font-size: 0.95rem;">
                                    <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-file-earmark-text me-2"></i>Registration Summary</h6>
                                    <div id="reviewContent"></div>
                                </div>
                                <div class="form-check mb-4 p-3 bg-white border rounded">
                                    <input class="form-check-input ms-1 me-3" type="checkbox" id="data_privacy_agreement" value="1" required style="transform: scale(1.3); margin-top: 5px;">
                                    <label class="form-check-label fw-semibold" for="data_privacy_agreement" style="font-size: 0.9rem; margin-left: 0.5rem; display: block;">
                                        I agree to the Data Privacy Act of 2012 (Republic Act No. 10173). I hereby give my consent to the Occupational Safety and Health Center (OSHC) to collect, process, and store my personal and organizational information for the purpose of accreditation reporting and monitoring.
                                    </label>
                                    <div class="invalid-feedback ms-4 mt-2">You must agree to the Data Privacy Act before submitting.</div>
                                </div>
                            </div>

                            {{-- Submit / Review Buttons --}}
                            <div class="d-grid mt-4 gap-2 d-md-flex justify-content-md-end">
                                <button type="button" id="reviewBtn" class="btn btn-outline-primary btn-lg fw-semibold" style="border-radius:10px;padding:.85rem 1.5rem;">
                                    <i class="bi bi-search me-2"></i> Review Details
                                </button>
                                <button type="button" id="backBtn" class="btn btn-outline-secondary btn-lg fw-semibold d-none" style="border-radius:10px;padding:.85rem 1.5rem;">
                                    <i class="bi bi-arrow-left me-2"></i> Edit Details
                                </button>
                                <button type="submit" id="submitBtn" class="btn btn-primary btn-lg fw-semibold d-none"
                                    style="background:var(--blue-deep);border-color:var(--blue-deep);
                                        border-radius:10px;padding:.85rem 1.5rem;">
                                    <span id="submitBtnText">
                                        <i class="bi bi-check2-circle me-2"></i> Submit Registration
                                    </span>
                                    <span id="submitBtnSpinner" class="d-none">
                                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                        Sending verification emailâ€¦
                                    </span>
                                </button>
                            </div>


                    </div>{{-- /#formSections --}}

                    </form>

                    {{--  Hidden template card (cloned by JS). Kept outside form so its 'required' attributes don't block checkValidity().  --}}
                    <div id="instructorTemplate" class="d-none" aria-hidden="true">
                        <div class="instructor-card border rounded-3 bg-white shadow-sm p-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="fw-bold mb-0" style="color:#0b3d91;">
                                    <i class="bi bi-person-fill me-2"></i>
                                    <span class="instructor-label">Instructor #1</span>
                                </h6>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-instructor-btn d-none">
                                    <i class="bi bi-trash me-1"></i>Remove
                                </button>
                            </div>

                            {{-- Personal Info --}}
                            <p class="fw-semibold mb-2" style="font-size:.82rem;color:#555;text-transform:uppercase;letter-spacing:.05em;">Personal Information</p>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size:.88rem;">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][first_name]" placeholder="Juan" required>
                                    <div class="invalid-feedback">First name is required.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size:.88rem;">Middle Name</label>
                                    <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][middle_name]" placeholder="(optional)">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size:.88rem;">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][last_name]" placeholder="Dela Cruz" required>
                                    <div class="invalid-feedback">Last name is required.</div>
                                </div>
                            </div>

                            {{-- TESDA EMS NC II/III --}}
                            <div class="credential-block border rounded-2 p-3 mb-2" style="background:#f8f9ff;">
                                <p class="fw-bold mb-2" style="font-size:.83rem;color:#0b3d91;"><span class="badge me-1" style="background:#0b3d91;font-size:.7rem;">EMS</span>TESDA Emergency Medical Services NC II or III Certificate</p>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Certificate Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][EMS][number]" placeholder="e.g. NC-EMS-2024-00001" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Certificate number is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Issued Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][EMS][issued_date]" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Issued date is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Validity Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][EMS][validity_date]" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Validity date is required.</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF <span class="text-danger">*</span></label>
                                        <div class="file-upload-wrapper">
                                            <input class="real-file-input visually-hidden" type="file" name="instructors[__IDX__][credentials][EMS][pdf]" id="inst_ems_pdf___IDX__" accept=".pdf" required>
                                            <div class="d-flex align-items-center gap-2">
                                                <label for="inst_ems_pdf___IDX__" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn"><i class="bi bi-cloud-upload me-1"></i>Choose File</label>
                                                <span class="file-name-text text-muted" style="font-size:.78rem;">No file chosen</span>
                                            </div>
                                            <div class="invalid-feedback file-invalid-feedback" style="font-size:0.8rem;margin-top:4px;">Please upload the EMS certificate PDF.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- TESDA TM1 --}}
                            <div class="credential-block border rounded-2 p-3 mb-2" style="background:#f8f9ff;">
                                <p class="fw-bold mb-2" style="font-size:.83rem;color:#0b3d91;"><span class="badge me-1" style="background:#0b3d91;font-size:.7rem;">TM1</span>TESDA TM1</p>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Certificate Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][TM1][number]" placeholder="e.g. TM1-2024-00001" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Certificate number is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Issued Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][TM1][issued_date]" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Issued date is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Validity Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][TM1][validity_date]" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Validity date is required.</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF <span class="text-danger">*</span></label>
                                        <div class="file-upload-wrapper">
                                            <input class="real-file-input visually-hidden" type="file" name="instructors[__IDX__][credentials][TM1][pdf]" id="inst_tm1_pdf___IDX__" accept=".pdf" required>
                                            <div class="d-flex align-items-center gap-2">
                                                <label for="inst_tm1_pdf___IDX__" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn"><i class="bi bi-cloud-upload me-1"></i>Choose File</label>
                                                <span class="file-name-text text-muted" style="font-size:.78rem;">No file chosen</span>
                                            </div>
                                            <div class="invalid-feedback file-invalid-feedback" style="font-size:0.8rem;margin-top:4px;">Please upload the TM1 certificate PDF.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- TESDA NTTC --}}
                            <div class="credential-block border rounded-2 p-3 mb-2" style="background:#f8f9ff;">
                                <p class="fw-bold mb-2" style="font-size:.83rem;color:#0b3d91;"><span class="badge me-1" style="background:#0b3d91;font-size:.7rem;">NTTC</span>TESDA NTTC</p>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Certificate Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][NTTC][number]" placeholder="e.g. NTTC-2024-00001" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Certificate number is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Issued Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][NTTC][issued_date]" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Issued date is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Validity Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][NTTC][validity_date]" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Validity date is required.</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF <span class="text-danger">*</span></label>
                                        <div class="file-upload-wrapper">
                                            <input class="real-file-input visually-hidden" type="file" name="instructors[__IDX__][credentials][NTTC][pdf]" id="inst_nttc_pdf___IDX__" accept=".pdf" required>
                                            <div class="d-flex align-items-center gap-2">
                                                <label for="inst_nttc_pdf___IDX__" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn"><i class="bi bi-cloud-upload me-1"></i>Choose File</label>
                                                <span class="file-name-text text-muted" style="font-size:.78rem;">No file chosen</span>
                                            </div>
                                            <div class="invalid-feedback file-invalid-feedback" style="font-size:0.8rem;margin-top:4px;">Please upload the NTTC certificate PDF.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- BOSH SO1/SO2 --}}
                            <div class="credential-block border rounded-2 p-3 mb-2" style="background:#f8f9ff;">
                                <p class="fw-bold mb-2" style="font-size:.83rem;color:#0b3d91;"><span class="badge me-1" style="background:#0b3d91;font-size:.7rem;">BOSH</span>BOSH SO1/SO2</p>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Certificate Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][BOSH][number]" placeholder="e.g. BOSH-2024-00001" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Certificate number is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Validity Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][BOSH][validity_date]" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Validity date is required.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Training Date(s) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][BOSH][training_dates]" placeholder="e.g. Jan 10-12, 2024" required>
                                        <div class="invalid-feedback" style="font-size:.78rem;">Training date(s) are required.</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF <span class="text-danger">*</span></label>
                                        <div class="file-upload-wrapper">
                                            <input class="real-file-input visually-hidden" type="file" name="instructors[__IDX__][credentials][BOSH][pdf]" id="inst_bosh_pdf___IDX__" accept=".pdf" required>
                                            <div class="d-flex align-items-center gap-2">
                                                <label for="inst_bosh_pdf___IDX__" class="btn btn-outline-primary btn-sm mb-0 px-3 fw-semibold custom-file-btn"><i class="bi bi-cloud-upload me-1"></i>Choose File</label>
                                                <span class="file-name-text text-muted" style="font-size:.78rem;">No file chosen</span>
                                            </div>
                                            <div class="invalid-feedback file-invalid-feedback" style="font-size:0.8rem;margin-top:4px;">Please upload the BOSH certificate PDF.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Service Agreement --}}
                            <div class="credential-block border rounded-2 p-3" style="background:#fffdf4;border-color:#d4ac4b !important;">
                                <p class="fw-bold mb-2" style="font-size:.83rem;color:#7a5c00;"><i class="bi bi-file-earmark-text-fill me-1"></i>Service Agreement</p>
                                <div class="file-upload-wrapper">
                                    <input class="real-file-input visually-hidden" type="file" name="instructors[__IDX__][service_agreement]" id="inst_sa___IDX__" accept=".pdf" required>
                                    <div class="d-flex align-items-center gap-2">
                                        <label for="inst_sa___IDX__" class="btn btn-sm mb-0 px-3 fw-semibold custom-file-btn" style="border:1px solid #d4ac4b;color:#7a5c00;"><i class="bi bi-cloud-upload me-1"></i>Choose PDF</label>
                                        <span class="file-name-text text-muted" style="font-size:.78rem;">No file chosen</span>
                                    </div>
                                    <div class="invalid-feedback file-invalid-feedback" style="font-size:0.8rem;margin-top:4px;">Please upload the Service Agreement PDF.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="reg-login-link">
                        Already have an account?
                        <a href="{{ route('login') }}">Sign in here</a>
                    </div>

                </div> {{-- /.reg-card-body --}}
            </div> {{-- /.reg-card --}}

        </div>
    </div>
</div>
</div>
@endsection
