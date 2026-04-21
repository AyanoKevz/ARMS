@extends('layouts.landing')

@section('title', 'Register | ARMS')

@section('content')
<div class="register-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9 mx-auto" style="max-width: 900px;">

                <div class="reg-card">

                    {{-- ── Card Header ── --}}
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

                    {{-- ── Card Body / Form ── --}}
                    <div class="reg-card-body">

                        {{-- Dynamic Alert (Fixed Top) --}}
                        <div id="dynamicAlert" class="alert d-none alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 350px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                            <span id="dynamicAlertMessage"></span>
                            <button type="button" class="btn-close" aria-label="Close" onclick="document.getElementById('dynamicAlert').classList.add('d-none')"></button>
                        </div>

                        <div id="emailSentPanel" class="d-none text-center py-4">
                            <div style="font-size:3.5rem;margin-bottom:1rem;animation:pulse 2s ease-in-out infinite;">✉️</div>
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

                        {{-- ── Registration Form ── --}}
                        <form id="registerForm" action="{{ route('register.store') }}" method="POST" enctype="multipart/form-data" novalidate>
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

                                <p class="form-section-title mt-4">Step 4 — Authorized Representative</p>
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

                            <p class="form-section-title mt-4">Step 5 — Submission of Required Documents</p>
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

                                {{-- ── Type 1: Legal Requirements to Operate Business ── --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">1</span>Legal Requirements to Operate Business</h6>
                                        <div class="row g-3">
                                            @foreach([
                                                ['code'=>'LEGAL_01','label'=>'Business Permit / Mayor\'s Permit'],
                                                ['code'=>'LEGAL_02','label'=>'SEC / DTI / CDA Registration Certificate'],
                                                ['code'=>'LEGAL_03','label'=>'BIR Certificate of Registration'],
                                                ['code'=>'LEGAL_04','label'=>'SSS / PhilHealth / Pag-IBIG Registration'],
                                                ['code'=>'LEGAL_05','label'=>'Organizational Chart'],
                                                ['code'=>'LEGAL_06','label'=>'Proof of Office / Training Facility Ownership or Lease'],
                                                ['code'=>'LEGAL_07','label'=>'List of Current Trainers and Staff'],
                                            ] as $f)
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold" style="font-size:.85rem;">{{ $f['label'] }} <span class="text-danger">*</span></label>
                                                <input class="form-control form-control-sm" type="file" name="documents[{{ $f['code'] }}]" accept=".pdf">
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                {{-- ── Type 2: Training Management and Staff ── --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">2</span>Training Management and Staff</h6>
                                        <div class="row g-3">
                                            @foreach([
                                                ['code'=>'TRAIN_01','label'=>'Training Program / Course Outline'],
                                                ['code'=>'TRAIN_02','label'=>'Trainer\'s Certificates / Credentials'],
                                                ['code'=>'TRAIN_03','label'=>'Staff Job Descriptions or Employment Contracts'],
                                            ] as $f)
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold" style="font-size:.85rem;">{{ $f['label'] }} <span class="text-danger">*</span></label>
                                                <input class="form-control form-control-sm" type="file" name="documents[{{ $f['code'] }}]" accept=".pdf">
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                {{-- ── Type 3: Premises Including Occupational Safety ── --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">3</span>Premises Including Occupational Safety</h6>
                                        <div class="row g-3">
                                            @foreach([
                                                ['code'=>'PREM_01','label'=>'Floor Plan of Training Facility'],
                                                ['code'=>'PREM_02','label'=>'Occupancy Permit'],
                                                ['code'=>'PREM_03','label'=>'Fire Safety Inspection Certificate'],
                                                ['code'=>'PREM_04','label'=>'Sanitary Permit'],
                                                ['code'=>'PREM_05','label'=>'Emergency Evacuation Plan'],
                                                ['code'=>'PREM_06','label'=>'Electrical Inspection Certificate'],
                                                ['code'=>'PREM_07','label'=>'Photos of Training Premises'],
                                            ] as $f)
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold" style="font-size:.85rem;">{{ $f['label'] }} <span class="text-danger">*</span></label>
                                                <input class="form-control form-control-sm" type="file" name="documents[{{ $f['code'] }}]" accept=".pdf">
                                            </div>
                                            @endforeach
                                            {{-- 1 Date input --}}
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold" style="font-size:.85rem;">Date of Last Safety Inspection <span class="text-danger">*</span></label>
                                                <input class="form-control form-control-sm" type="date" name="documents[PREM_DATE]">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- ── Type 4: Policies on IP and Data Protection ── --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">4</span>Policies on Intellectual Property and Data Protection</h6>
                                        <div class="row g-3">
                                            {{-- 1 Text --}}
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold" style="font-size:.85rem;">Name of Data Protection Officer <span class="text-danger">*</span></label>
                                                <input class="form-control form-control-sm" type="text" name="documents[IP_DPO_NAME]" placeholder="Full name of DPO">
                                            </div>
                                            {{-- 2 File --}}
                                            @foreach([
                                                ['code'=>'IP_01','label'=>'Data Privacy Policy Document'],
                                                ['code'=>'IP_02','label'=>'Intellectual Property Policy Document'],
                                            ] as $f)
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold" style="font-size:.85rem;">{{ $f['label'] }} <span class="text-danger">*</span></label>
                                                <input class="form-control form-control-sm" type="file" name="documents[{{ $f['code'] }}]" accept=".pdf">
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                {{-- ── Type 5: Quality Assurance and Enhancement ── --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">5</span>Quality Assurance and Enhancement</h6>
                                        <div class="row g-3">
                                            @foreach([
                                                ['code'=>'QA_01','label'=>'Quality Management Manual / Handbook'],
                                                ['code'=>'QA_02','label'=>'Internal Audit Reports'],
                                                ['code'=>'QA_03','label'=>'Trainee Feedback / Evaluation Forms'],
                                                ['code'=>'QA_04','label'=>'Post-Training Evaluation Results'],
                                                ['code'=>'QA_05','label'=>'Certificates Issued to Graduates (sample)'],
                                                ['code'=>'QA_06','label'=>'Annual Report or Operations Report'],
                                                ['code'=>'QA_07','label'=>'List of Completed Trainings / Batches'],
                                                ['code'=>'QA_08','label'=>'Corrective Action Plan (if applicable)'],
                                                ['code'=>'QA_09','label'=>'Continuous Improvement Documentation'],
                                            ] as $f)
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold" style="font-size:.85rem;">{{ $f['label'] }} <span class="text-danger">*</span></label>
                                                <input class="form-control form-control-sm" type="file" name="documents[{{ $f['code'] }}]" accept=".pdf">
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                {{-- ── Type 6: Training Equipment and Materials ── --}}
                                <div class="col-12">
                                    <div class="doc-type-section p-3 border rounded-3 bg-white shadow-sm">
                                        <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">6</span>Training Equipment and Materials</h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold" style="font-size:.85rem;">Inventory of Training Equipment and Materials <span class="text-danger">*</span></label>
                                                <input class="form-control form-control-sm" type="file" name="documents[EQUIP_01]" accept=".pdf">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>


                            <div id="reviewSection" class="d-none mt-4">
                                <p class="form-section-title">Step 6 — Review & Submit</p>
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
                                        Sending verification email…
                                    </span>
                                </button>
                            </div>


                    </div>{{-- /#formSections --}}

                    </form>

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
@push('scripts')

<style>
    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.08);
        }
    }
</style>
@endpush

@endsection