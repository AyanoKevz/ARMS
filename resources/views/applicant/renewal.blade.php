@extends('layouts.applicant')

@section('title', 'Renewal / Reinstatement')

@section('content')
@php
    $isOrg = $user->profile_type === 'Organization';
    $org   = $user->organizationProfile;
    $ind   = $user->individualProfile;
    $reps  = $org?->authorizedRepresentatives ?? collect();
    $rep   = $reps->first();
    $instructors = $user->instructors;
@endphp

<div class="">
    <div class="page-title d-flex justify-content-between align-items-center">
        <div class="title_left"><h3>Renewal / Reinstatement</h3></div>
        <a href="{{ route('applicant.dashboard') }}" class="btn btn-secondary btn-sm mt-3">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
    <div class="clearfix"></div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- Guard: already has pending renewal --}}
    @if($pendingRenewal)
    <div class="x_panel" style="border-left:4px solid #ffc107;">
        <div class="x_content py-4 text-center">
            <i class="fas fa-info-circle fa-3x text-warning mb-3"></i>
            <h5 class="fw-bold">You already have a pending {{ ucfirst($pendingRenewal->application_type) }} application</h5>
            <p class="text-muted">Tracking Number: <strong>{{ $pendingRenewal->tracking_number }}</strong></p>
            <p class="text-muted mb-0">Please wait for admin evaluation before submitting another.</p>
        </div>
    </div>
    @else

    {{-- Guard: no accreditation --}}
    @if(!$accreditation)
    <div class="x_panel" style="border-left:4px solid #dc3545;">
        <div class="x_content py-4 text-center">
            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
            <h5 class="fw-bold">No Accreditation Found</h5>
            <p class="text-muted mb-0">You must have an existing accreditation to apply for renewal or reinstatement.</p>
        </div>
    </div>
    @else

    {{-- Accreditation Summary --}}
    <div class="x_panel" style="border-left:4px solid var(--portal-gold, #d4ac4b); border-top:none;">
        <div class="x_title border-0 mb-0 pb-0">
            <h2 class="fw-bold" style="color:#2A3F54;"><i class="fas fa-award text-warning me-2"></i>Current Accreditation</h2>
            <div class="clearfix"></div>
        </div>
        <div class="x_content mt-2">
            <div class="row text-center text-md-start">
                <div class="col-md-3 mb-2 border-end">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Accreditation Number</p>
                    <p class="fw-bold fs-5 mb-0" style="color:#0b3d91;">{{ $accreditation->accreditation_number ?? 'N/A' }}</p>
                </div>
                <div class="col-md-3 mb-2 border-end">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Date Accredited</p>
                    <p class="fw-bold fs-5 mb-0" style="color:#2A3F54;">{{ $accreditation->date_of_accreditation ? \Carbon\Carbon::parse($accreditation->date_of_accreditation)->format('F d, Y') : 'N/A' }}</p>
                </div>
                <div class="col-md-3 mb-2 border-end">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Validity Period</p>
                    <p class="fw-bold fs-5 mb-0" style="color:#2A3F54;">{{ $accreditation->validity_date ? \Carbon\Carbon::parse($accreditation->validity_date)->format('F d, Y') : 'N/A' }}</p>
                </div>
                <div class="col-md-3">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Status</p>
                    @if($accreditation->status === 'active')
                        <span class="badge bg-success" style="font-size:.9rem;padding:6px 12px;">Active</span>
                    @elseif($accreditation->status === 'expired')
                        <span class="badge bg-warning text-dark" style="font-size:.9rem;padding:6px 12px;">Expired</span>
                    @elseif($accreditation->status === 'revoked')
                        <span class="badge bg-danger" style="font-size:.9rem;padding:6px 12px;">Revoked</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Renewal Form --}}
    <form action="{{ route('applicant.renewal.store') }}" method="POST" enctype="multipart/form-data" id="renewalForm">
        @csrf

        {{-- Step 1 — Application Type --}}
        <div class="x_panel">
            <div class="x_title"><h2><i class="fas fa-exchange-alt me-2"></i>Application Type</h2><div class="clearfix"></div></div>
            <div class="x_content">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="application_type" id="type_renewal" value="renewal" required checked>
                            <label class="form-check-label fw-semibold" for="type_renewal"><i class="fas fa-sync-alt text-info me-1"></i> Renewal</label>
                        </div>
                        <div class="form-check form-check-inline ms-4">
                            <input class="form-check-input" type="radio" name="application_type" id="type_reinstatement" value="reinstatement">
                            <label class="form-check-label fw-semibold" for="type_reinstatement"><i class="fas fa-redo text-secondary me-1"></i> Reinstatement</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 2 — Organization / Profile Info --}}
        @if($isOrg && $org)
        <div class="x_panel">
            <div class="x_title"><h2><i class="fas fa-building me-2"></i>Organization Information</h2><div class="clearfix"></div></div>
            <div class="x_content">
                <div class="row g-3">
                    <div class="col-md-12"><label class="form-label fw-semibold">Name of FATPro <span class="text-danger">*</span></label><input type="text" class="form-control" name="org_name" value="{{ old('org_name', $org->name) }}" required></div>
                    <div class="col-md-12"><label class="form-label fw-semibold">Complete Address <span class="text-danger">*</span></label><input type="text" class="form-control" name="org_address" value="{{ old('org_address', $org->address) }}" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Head / Director <span class="text-danger">*</span></label><input type="text" class="form-control" name="head_name" value="{{ old('head_name', $org->head_name) }}" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Designation</label><input type="text" class="form-control" name="designation" value="{{ old('designation', $org->designation) }}"></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Telephone</label><input type="text" class="form-control" name="telephone" value="{{ old('telephone', $org->telephone) }}"></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Fax</label><input type="text" class="form-control" name="fax" value="{{ old('fax', $org->fax) }}"></div>
                    <div class="col-md-12"><label class="form-label fw-semibold">Organization Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="org_email" value="{{ old('org_email', $org->email) }}" required></div>
                </div>

                <hr>
                <h6 class="fw-bold mb-3"><i class="fas fa-user-tie me-2"></i>Authorized Representative</h6>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="rep_full_name" value="{{ old('rep_full_name', $rep?->full_name) }}" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Position <span class="text-danger">*</span></label><input type="text" class="form-control" name="rep_position" value="{{ old('rep_position', $rep?->position) }}" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Contact Number <span class="text-danger">*</span></label><input type="text" class="form-control" name="rep_contact_number" value="{{ old('rep_contact_number', $rep?->contact_number) }}" required maxlength="11"></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="rep_email" value="{{ old('rep_email', $rep?->email) }}" required></div>
                </div>
            </div>
        </div>
        @endif

        {{-- Step 3 — Instructors & Credentials --}}
        <div class="x_panel">
            <div class="x_title"><h2><i class="fas fa-chalkboard-teacher me-2"></i>Instructors & Credentials</h2><div class="clearfix"></div></div>
            <div class="x_content">
                <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Add at least <strong>one instructor</strong>. Each requires credentials and a service agreement PDF.</div>
                <div id="instructorCardsContainer">
                    @foreach($instructors as $idx => $inst)
                    <div class="instructor-card border rounded-3 bg-white shadow-sm p-3 mb-3" data-idx="{{ $idx }}">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold mb-0" style="color:#0b3d91;"><i class="fas fa-user me-2"></i>Instructor #{{ $idx + 1 }}</h6>
                            @if($idx > 0)<button type="button" class="btn btn-sm btn-outline-danger remove-instructor-btn"><i class="fas fa-trash me-1"></i>Remove</button>@endif
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[{{ $idx }}][first_name]" value="{{ $inst->first_name }}" required></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Middle Name</label><input type="text" class="form-control form-control-sm" name="instructors[{{ $idx }}][middle_name]" value="{{ $inst->middle_name }}"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[{{ $idx }}][last_name]" value="{{ $inst->last_name }}" required></div>
                        </div>

                        @foreach(['EMS' => 'TESDA EMS NC II/III', 'TM1' => 'TESDA TM1', 'NTTC' => 'TESDA NTTC', 'BOSH' => 'BOSH SO1/SO2'] as $type => $label)
                        @php $cred = $inst->credentials->firstWhere('type', $type); @endphp
                        <div class="border rounded-2 p-3 mb-2" style="background:#f8f9ff;">
                            <p class="fw-bold mb-2" style="font-size:.83rem;color:#0b3d91;"><span class="badge me-1" style="background:#0b3d91;font-size:.7rem;">{{ $type }}</span>{{ $label }}</p>
                            <div class="row g-2">
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Certificate Number</label><input type="text" class="form-control form-control-sm" name="instructors[{{ $idx }}][credentials][{{ $type }}][number]" value="{{ $cred?->number }}"></div>
                                @if($type !== 'BOSH')
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Issued Date</label><input type="date" class="form-control form-control-sm" name="instructors[{{ $idx }}][credentials][{{ $type }}][issued_date]" value="{{ $cred?->issued_date }}"></div>
                                @endif
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Validity Date</label><input type="date" class="form-control form-control-sm" name="instructors[{{ $idx }}][credentials][{{ $type }}][validity_date]" value="{{ $cred?->validity_date }}"></div>
                                @if($type === 'BOSH')
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Training Dates</label><input type="text" class="form-control form-control-sm" name="instructors[{{ $idx }}][credentials][{{ $type }}][training_dates]" value="{{ $cred?->training_dates }}"></div>
                                @endif
                                <div class="col-12"><label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF @if($cred?->pdf_path)<span class="text-success">(current: {{ basename($cred->pdf_path) }})</span>@endif</label><input type="file" class="form-control form-control-sm" name="instructors[{{ $idx }}][credentials][{{ $type }}][pdf]" accept=".pdf"></div>
                            </div>
                        </div>
                        @endforeach

                        <div class="border rounded-2 p-3" style="background:#fffdf4;border-color:#d4ac4b !important;">
                            <p class="fw-bold mb-2" style="font-size:.83rem;color:#7a5c00;"><i class="fas fa-file-contract me-1"></i>Service Agreement @if($inst->service_agreement_path)<span class="text-success">(current: {{ basename($inst->service_agreement_path) }})</span>@endif</p>
                            <input type="file" class="form-control form-control-sm" name="instructors[{{ $idx }}][service_agreement]" accept=".pdf">
                        </div>
                    </div>
                    @endforeach

                    @if($instructors->isEmpty())
                    <div class="instructor-card border rounded-3 bg-white shadow-sm p-3 mb-3" data-idx="0">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold mb-0" style="color:#0b3d91;"><i class="fas fa-user me-2"></i>Instructor #1</h6>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[0][first_name]" required></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Middle Name</label><input type="text" class="form-control form-control-sm" name="instructors[0][middle_name]"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[0][last_name]" required></div>
                        </div>
                        @foreach(['EMS' => 'TESDA EMS NC II/III', 'TM1' => 'TESDA TM1', 'NTTC' => 'TESDA NTTC', 'BOSH' => 'BOSH SO1/SO2'] as $type => $label)
                        <div class="border rounded-2 p-3 mb-2" style="background:#f8f9ff;">
                            <p class="fw-bold mb-2" style="font-size:.83rem;color:#0b3d91;"><span class="badge me-1" style="background:#0b3d91;font-size:.7rem;">{{ $type }}</span>{{ $label }}</p>
                            <div class="row g-2">
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Certificate Number</label><input type="text" class="form-control form-control-sm" name="instructors[0][credentials][{{ $type }}][number]"></div>
                                @if($type !== 'BOSH')<div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Issued Date</label><input type="date" class="form-control form-control-sm" name="instructors[0][credentials][{{ $type }}][issued_date]"></div>@endif
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Validity Date</label><input type="date" class="form-control form-control-sm" name="instructors[0][credentials][{{ $type }}][validity_date]"></div>
                                @if($type === 'BOSH')<div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Training Dates</label><input type="text" class="form-control form-control-sm" name="instructors[0][credentials][{{ $type }}][training_dates]"></div>@endif
                                <div class="col-12"><label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF</label><input type="file" class="form-control form-control-sm" name="instructors[0][credentials][{{ $type }}][pdf]" accept=".pdf"></div>
                            </div>
                        </div>
                        @endforeach
                        <div class="border rounded-2 p-3" style="background:#fffdf4;border-color:#d4ac4b !important;">
                            <p class="fw-bold mb-2" style="font-size:.83rem;color:#7a5c00;"><i class="fas fa-file-contract me-1"></i>Service Agreement</p>
                            <input type="file" class="form-control form-control-sm" name="instructors[0][service_agreement]" accept=".pdf">
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Step 4 — Document Uploads --}}
        <div class="x_panel">
            <div class="x_title"><h2><i class="fas fa-file-upload me-2"></i>Required Documents</h2><div class="clearfix"></div></div>
            <div class="x_content">
                <div class="alert alert-info mb-3"><i class="fas fa-info-circle me-2"></i>Upload updated documents in <strong>PDF format</strong> (max 10 MB). Leave empty to keep the current file.</div>

                @php
                $docSections = [
                    ['title' => 'Legal Requirements to Operate Business', 'badge' => '1', 'docs' => [
                        ['code'=>'LEGAL_01','title'=>'DOLE Registration','required'=>true],
                        ['code'=>'LEGAL_02','title'=>'Business Registration','required'=>true],
                        ['code'=>'LEGAL_03','title'=>'Articles of Incorporation','required'=>true],
                        ['code'=>'LEGAL_04','title'=>"Mayor's Permit",'required'=>true],
                        ['code'=>'LEGAL_05','title'=>'BIR Registration & TIN','required'=>true],
                        ['code'=>'LEGAL_06','title'=>'DOLE clearance','required'=>true],
                        ['code'=>'LEGAL_07','title'=>'Lease/Ownership Agreement','required'=>false],
                    ]],
                    ['title' => 'Training Management and Staff', 'badge' => '2', 'docs' => [
                        ['code'=>'TRAIN_01','title'=>'Organizational Chart','required'=>true],
                        ['code'=>'TRAIN_02','title'=>'TESDA Certificate','required'=>false],
                        ['code'=>'TRAIN_03','title'=>'Training Monitoring','required'=>true],
                    ]],
                    ['title' => 'Premises Including Occupational Safety', 'badge' => '3', 'docs' => [
                        ['code'=>'PREM_01','title'=>'Location Map','required'=>true],
                        ['code'=>'PREM_02','title'=>'Site Floor Plan','required'=>true],
                        ['code'=>'PREM_03','title'=>'OSH Policy & Program','required'=>true],
                        ['code'=>'PREM_04','title'=>'Decontamination Procedures','required'=>true],
                        ['code'=>'PREM_05','title'=>'Safety Officers List','required'=>true],
                        ['code'=>'PREM_06','title'=>'First-Aiders List','required'=>true],
                        ['code'=>'PREM_07','title'=>'First-Aider Certificate','required'=>true],
                    ]],
                    ['title' => 'Policies on IP and Data Protection', 'badge' => '4', 'docs' => [
                        ['code'=>'IP_01','title'=>'Data Privacy Policy','required'=>true],
                        ['code'=>'IP_02','title'=>'Intellectual Property Policy','required'=>true],
                    ]],
                    ['title' => 'Quality Assurance and Enhancement', 'badge' => '5', 'docs' => [
                        ['code'=>'QA_01','title'=>'Course Review Procedures','required'=>false],
                        ['code'=>'QA_02','title'=>'Test Results Summary','required'=>true],
                        ['code'=>'QA_03','title'=>'Evaluation Summary','required'=>true],
                        ['code'=>'QA_04','title'=>'Assessment Tools','required'=>true],
                        ['code'=>'QA_05','title'=>'Participant Directory Template','required'=>true],
                        ['code'=>'QA_06','title'=>'Attendance Sheet Template','required'=>true],
                        ['code'=>'QA_07','title'=>'Emergency First Aid Manual','required'=>true],
                        ['code'=>'QA_08','title'=>'Occupational First Aid Manual','required'=>true],
                        ['code'=>'QA_09','title'=>'Standard First Aid Manual','required'=>true],
                    ]],
                    ['title' => 'Training Equipment and Materials', 'badge' => '6', 'docs' => [
                        ['code'=>'EQUIP_01','title'=>'Equipment & Materials List','required'=>true],
                    ]],
                ];
                @endphp

                @foreach($docSections as $section)
                <div class="border rounded-3 bg-white shadow-sm p-3 mb-3">
                    <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">{{ $section['badge'] }}</span>{{ $section['title'] }}</h6>
                    <div class="row g-3">
                        @if($section['badge'] == '4')
                        <div class="col-md-12 mb-2">
                            <label class="form-label fw-bold mb-1" style="font-size:.88rem;">Data Protection Officer Name</label>
                            @php $existingDPO = $existingDocs->get('IP_DPO_NAME'); @endphp
                            @if($existingDPO && $existingDPO->value)
                                <div class="form-text mt-0 mb-1" style="font-size:.75rem;color:#198754;"><i class="fas fa-check-circle me-1"></i>Current: {{ Str::limit($existingDPO->value, 30) }}</div>
                            @endif
                            <input type="text" class="form-control form-control-sm" name="documents[IP_DPO_NAME]" value="{{ $existingDPO?->value }}" placeholder="Full name of DPO">
                        </div>
                        @endif

                        @foreach($section['docs'] as $f)
                        @php $existing = $existingDocs->get($f['code']); @endphp
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-bold mb-1" style="font-size:.88rem;">{{ $f['title'] }}</label>
                            @if($existing && $existing->file_path)
                                <div class="form-text mt-0 mb-1" style="font-size:.75rem;color:#198754;"><i class="fas fa-check-circle me-1"></i>Current: {{ basename($existing->file_path) }}</div>
                            @elseif($existing && $existing->value)
                                <div class="form-text mt-0 mb-1" style="font-size:.75rem;color:#198754;"><i class="fas fa-check-circle me-1"></i>Value: {{ Str::limit($existing->value, 30) }}</div>
                            @endif
                            <input type="file" class="form-control form-control-sm" name="documents[{{ $f['code'] }}]" accept=".pdf">
                        </div>
                        @endforeach

                        @if($section['badge'] == '3')
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-bold mb-1" style="font-size:.88rem;">Certificate Validity Date</label>
                            @php $existingPremDate = $existingDocs->get('PREM_DATE'); @endphp
                            @if($existingPremDate && $existingPremDate->value)
                                <div class="form-text mt-0 mb-1" style="font-size:.75rem;color:#198754;"><i class="fas fa-check-circle me-1"></i>Current: {{ $existingPremDate->value }}</div>
                            @endif
                            <input type="date" class="form-control form-control-sm" name="documents[PREM_DATE]" value="{{ $existingPremDate?->value }}">
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach

                {{-- Special fields: PREM_DATE and IP_DPO_NAME --}}
            </div>
        </div>

        {{-- Submit --}}
        <div class="text-center py-4 mb-4">
            <button type="submit" class="btn btn-primary btn-lg fw-bold px-5" style="border-radius:10px; box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);">
                <i class="fas fa-paper-plane me-2"></i>Submit Application
            </button>
            <p class="text-muted mt-2 mb-0" style="font-size:.85rem;">By submitting, your old files will be replaced with the newly uploaded ones.</p>
        </div>
    </form>
    @endif
    @endif
</div>
@endsection
