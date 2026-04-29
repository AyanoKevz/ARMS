@extends('layouts.admin')

@section('title', 'Application – ' . $application->tracking_number)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/show-application.css') }}">
@endpush

@section('content')

@php
    $user  = $application->user;
    $isOrg = $user->profile_type === 'Organization';
    $org   = $user->organizationProfile;
    $ind   = $user->individualProfile;
    $reps  = $org?->authorizedRepresentatives ?? collect();

    $grouped = $application->documents->groupBy(
        fn($doc) => optional($doc->documentField?->documentType)->id
    );

    $currentStatus  = $application->latestStatus?->status?->name ?? 'Under Evaluation';
    $isScheduled    = $currentStatus === 'Scheduled for Interview';
    $allApproved    = $application->documents->count() > 0
                      && $application->documents->every(fn($d) => $d->status === 'approved');

    $interview      = $application->interview;
    $isAccredited   = (bool) $application->accreditation;
    $isApproved     = $currentStatus === 'Approved';
    $isRejected     = $currentStatus === 'Rejected';
@endphp

{{-- ── Flash Messages ── --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ── Page Header ── --}}
<div class="page-title d-flex justify-content-between align-items-center">
    <div class="title_left"><h3>Application Details</h3></div>
    <a href="{{ url()->previous() }}" class="btn btn-secondary btn-sm mt-3">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>
<div class="clearfix"></div>

{{-- ── Tracking Strip ── --}}
<div class="ai-card d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <div class="lbl" style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:#999;letter-spacing:.45px;">Tracking Number</div>
        <h4 class="m-0 fw-bold" style="color:#2A3F54;">{{ $application->tracking_number }}</h4>
        <small class="text-muted"><i class="bi bi-calendar3 me-1"></i>Submitted: {{ $application->created_at->format('F d, Y h:i A') }}</small>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
        @php
            $statusColor = match($currentStatus) {
                'Scheduled for Interview' => 'bg-primary',
                'For Update'              => 'bg-warning text-dark',
                'Approved'                => 'bg-success',
                'Rejected'                => 'bg-danger',
                default                   => 'bg-info',
            };
        @endphp
        <span id="app-status-badge" class="badge fs-6 px-3 py-2 {{ $statusColor }}">
            {{ $currentStatus }}
        </span>
        <small class="text-muted">
            {{ ucfirst($application->application_type) }} Application &mdash;
            {{ $application->accreditationType->name ?? 'N/A' }}
        </small>
    </div>
</div>

{{-- ══ Org / Reps Card ══ --}}
<div class="ai-card mb-4">
    <div class="ai-card-header">
        <i class="bi bi-building fs-5 text-primary"></i>
        <h5>Organization Information</h5>
    </div>
    @if($isOrg && $org)
    <div class="row">
        <div class="col-md-3 col-6"><div class="info-pair"><div class="lbl">Organization Name</div><div class="val">{{ $org->name }}</div></div></div>
        <div class="col-md-3 col-6"><div class="info-pair"><div class="lbl">Head of Organization</div><div class="val">{{ $org->head_name ?? '—' }}</div></div></div>
        <div class="col-md-3 col-6"><div class="info-pair"><div class="lbl">Designation</div><div class="val">{{ $org->designation ?? '—' }}</div></div></div>
        <div class="col-md-3 col-6"><div class="info-pair"><div class="lbl">Organization Email</div><div class="val">{{ $org->email ?? '—' }}</div></div></div>
        <div class="col-md-3 col-6"><div class="info-pair"><div class="lbl">Telephone</div><div class="val">{{ $org->telephone ?? '—' }}</div></div></div>
        <div class="col-md-3 col-6"><div class="info-pair"><div class="lbl">Fax</div><div class="val">{{ $org->fax ?? '—' }}</div></div></div>
        <div class="col-md-6 col-12"><div class="info-pair mb-0"><div class="lbl">Address</div><div class="val">{{ $org->address ?? '—' }}</div></div></div>
    </div>
    @else
    <p class="text-muted small mb-0">Organization profile not found.</p>
    @endif

    <hr style="border:0; border-top:1px dashed #dde3ef; margin:16px -20px;">

    <div class="ai-card-header">
        <i class="bi bi-person-badge fs-5 text-success"></i>
        <h5>Authorized Representative(s)</h5>
    </div>
    @forelse($reps as $rep)
    <div class="rep-block">
        <div class="row">
            <div class="col-md-3 col-6"><div class="info-pair"><div class="lbl">Full Name</div><div class="val">{{ $rep->full_name }}</div></div></div>
            <div class="col-md-3 col-6"><div class="info-pair"><div class="lbl">Position</div><div class="val">{{ $rep->position ?? '—' }}</div></div></div>
            <div class="col-md-3 col-6"><div class="info-pair mb-0"><div class="lbl">Contact Number</div><div class="val">{{ $rep->contact_number ?? '—' }}</div></div></div>
            <div class="col-md-3 col-6"><div class="info-pair mb-0"><div class="lbl">Email</div><div class="val">{{ $rep->email ?? '—' }}</div></div></div>
        </div>
    </div>
    @empty
    <p class="text-muted small mb-0">No authorized representatives found.</p>
    @endforelse
</div>

{{-- ══ Evaluation Form ══ --}}
<form id="evaluation-form"
      data-url="{{ route('admin.hcd.applications.finalize_evaluation', $application->id) }}">
    @csrf

    {{-- ── Documents Card ── --}}
    <div class="ai-card">
        <div class="ai-card-header">
            <i class="bi bi-folder2-open fs-5" style="color:#8e44ad;"></i>
            <h5>Submitted Documents</h5>
            <span class="ms-auto small text-muted fst-italic" id="eval-progress-label"></span>
        </div>

        @if($application->documents->count() > 0)
        <div class="row g-3">
            @foreach($grouped as $typeId => $docs)
                @php $sectionName = optional($docs->first()?->documentField?->documentType)->name ?? 'General Documents'; @endphp
                <div class="col-md-6">
                    <div class="doc-section">
                        <div class="doc-section-header">
                            <i class="bi bi-folder-fill"></i> {{ $sectionName }}
                        </div>

                        @foreach($docs as $doc)
                        @php
                            $field     = $doc->documentField;
                            $userDoc   = $doc->userDocument;
                            $inputType = $field?->input_type ?? 'text';
                            $filePath  = $userDoc?->file_path;
                            $textVal   = $userDoc?->value;

                            // Normalise: treat anything not approved/rejected as pending for JS
                            $evalStatus = in_array($doc->status, ['approved','rejected']) ? $doc->status : 'pending';

                            $badgeClass = match($doc->status) {
                                'approved'     => 'doc-badge-approved',
                                'rejected'     => 'doc-badge-rejected',
                                'for_revision' => 'doc-badge-for_revision',
                                default        => 'doc-badge-pending',
                            };
                            $badgeLabel = match($doc->status) {
                                'approved'     => 'Approved',
                                'rejected'     => 'Rejected',
                                'for_revision' => 'For Revision',
                                default        => 'Pending',
                            };
                        @endphp
                        <div class="doc-row" id="doc-row-{{ $doc->id }}">
                            {{-- Hidden form inputs --}}
                            <input type="hidden" name="evaluations[{{ $doc->id }}][id]" value="{{ $doc->id }}">
                            <input type="hidden" name="evaluations[{{ $doc->id }}][status]"
                                   id="status-input-{{ $doc->id }}" value="{{ $evalStatus }}">

                            {{-- Field name --}}
                            <div class="doc-field-name">
                                @if($inputType === 'file') <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                @elseif($inputType === 'date') <i class="bi bi-calendar-event text-info me-1"></i>
                                @else <i class="bi bi-fonts text-secondary me-1"></i>
                                @endif
                                {{ $field?->name ?? 'Unknown Field' }}
                            </div>

                            {{-- Value display --}}
                            @if($inputType === 'date' && $textVal)
                                <div class="doc-value">{{ \Carbon\Carbon::parse($textVal)->format('M d, Y') }}</div>
                            @elseif($inputType === 'text' && $textVal)
                                <div class="doc-value">{{ $textVal }}</div>
                            @elseif($inputType === 'file')
                                <div class="doc-value text-muted" style="font-size:.75rem;">
                                    {{ $filePath ? basename($filePath) : 'No file' }}
                                </div>
                            @endif

                            {{-- Status badge --}}
                            <span class="doc-badge {{ $badgeClass }}" id="badge-{{ $doc->id }}">{{ $badgeLabel }}</span>

                            {{-- View button --}}
                            @if($inputType === 'file' && $filePath)
                            <div class="doc-actions">
                                <a href="{{ route('admin.hcd.documents.view', $doc->id) }}?v={{ $doc->updated_at->timestamp ?? time() }}"
                                   target="_blank" class="btn btn-outline-primary btn-xs px-2 py-0"
                                   style="font-size:.78rem;">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </div>
                            @endif

                            {{-- Approve / Reject buttons + Reject panel (hidden once all docs approved) --}}
                            @if(!$allApproved && !$isScheduled)
                            <div class="doc-eval-actions">
                                <button type="button"
                                        class="btn-eval btn-approve {{ $evalStatus === 'approved' ? 'active' : '' }}"
                                        data-doc-id="{{ $doc->id }}"
                                        onclick="setDocStatus({{ $doc->id }}, 'approved')">
                                    <i class="bi bi-check-circle-fill"></i> Approve
                                </button>
                                <button type="button"
                                        class="btn-eval btn-reject {{ $evalStatus === 'rejected' ? 'active' : '' }}"
                                        data-doc-id="{{ $doc->id }}"
                                        onclick="setDocStatus({{ $doc->id }}, 'rejected')">
                                    <i class="bi bi-x-circle-fill"></i> Reject
                                </button>
                            </div>
                            <div class="reject-panel" id="reject-panel-{{ $doc->id }}"
                                 style="{{ $evalStatus === 'rejected' ? '' : 'display:none;' }}">
                                <label class="reject-remarks-label">
                                    <i class="bi bi-pencil-square me-1"></i>Rejection Remarks <span class="text-muted">(optional)</span>
                                </label>
                                <textarea class="reject-remarks-input"
                                          name="evaluations[{{ $doc->id }}][remarks]"
                                          id="remarks-{{ $doc->id }}"
                                          placeholder="Explain why this document was rejected…"
                                          rows="2">{{ $doc->remarks }}</textarea>
                            </div>
                            @endif

                        </div>{{-- /doc-row --}}
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        @else
        <div class="alert alert-secondary text-center mb-0">
            No documents have been uploaded for this application.
        </div>
        @endif
    </div>

    {{-- ── Instructor Credentials Card ── --}}
    @if($application->user && $application->user->instructors && $application->user->instructors->count() > 0)
    <div class="ai-card mt-4">
        <div class="ai-card-header">
            <i class="bi bi-people-fill fs-5" style="color:#27ae60;"></i>
            <h5>Instructor Credentials</h5>
        </div>
        
        <div class="row g-3">
            @foreach($application->user->instructors as $instructor)
            <div class="col-md-12">
                <div class="doc-section">
                    <div class="doc-section-header">
                        <i class="bi bi-person-badge-fill"></i> {{ $instructor->first_name }} {{ $instructor->last_name }}
                    </div>
                    
                    {{-- Credentials --}}
                    @foreach($instructor->credentials as $credential)
                    @php
                        $evalStatusCred = in_array($credential->status, ['approved','rejected']) ? $credential->status : 'pending';
                        $badgeClassCred = match($credential->status) {
                            'approved'     => 'doc-badge-approved',
                            'rejected'     => 'doc-badge-rejected',
                            'for_revision' => 'doc-badge-for_revision',
                            default        => 'doc-badge-pending',
                        };
                        $badgeLabelCred = match($credential->status) {
                            'approved'     => 'Approved',
                            'rejected'     => 'Rejected',
                            'for_revision' => 'For Revision',
                            default        => 'Pending',
                        };
                    @endphp
                    <div class="doc-row" id="doc-row-cred-{{ $credential->id }}">
                        <input type="hidden" name="credential_evaluations[{{ $credential->id }}][id]" value="{{ $credential->id }}">
                        <input type="hidden" name="credential_evaluations[{{ $credential->id }}][status]" id="status-input-cred-{{ $credential->id }}" value="{{ $evalStatusCred }}">
                        
                        <div class="doc-field-name">
                            <div class="d-flex align-items-center mb-1">
                                <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                <span class="fw-bold">
                                    @if($credential->type === 'EMS')
                                        TESDA Emergency Medical Services NC II or III Certificate
                                    @elseif($credential->type === 'TM1')
                                        TESDA Trainers Methodology Certificate 1
                                    @elseif($credential->type === 'NTTC')
                                        TESDA National TVET Trainer Certificate
                                    @elseif($credential->type === 'BOSH')
                                        BOSH SO1 or SO2 Certificate
                                    @else
                                        {{ $credential->type }} Credential
                                    @endif
                                </span>
                            </div>
                            <div class="ms-4 text-muted" style="font-size: 0.78rem; line-height: 1.5;">
                                @if($credential->number)
                                    <div><strong style="color:#555;">Certificate Number:</strong> {{ $credential->number }}</div>
                                @endif
                                @if($credential->issued_date)
                                    <div><strong style="color:#555;">Issued On:</strong> {{ \Carbon\Carbon::parse($credential->issued_date)->format('M d, Y') }}</div>
                                @endif
                                @if($credential->validity_date)
                                    <div><strong style="color:#555;">Valid Until:</strong> {{ \Carbon\Carbon::parse($credential->validity_date)->format('M d, Y') }}</div>
                                @endif
                                @if($credential->training_dates)
                                    <div><strong style="color:#555;">Training date(s):</strong> {{ $credential->training_dates }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="doc-value text-muted" style="font-size:.75rem;">
                            {{ $credential->pdf_path ? basename($credential->pdf_path) : 'No file' }}
                        </div>
                        
                        <span class="doc-badge {{ $badgeClassCred }}" id="badge-cred-{{ $credential->id }}">{{ $badgeLabelCred }}</span>
                        
                        @if($credential->pdf_path)
                        <div class="doc-actions">
                            <a href="{{ route('admin.hcd.instructors.credentials.view', $credential->id) }}?v={{ $credential->updated_at->timestamp ?? time() }}" target="_blank" class="btn btn-outline-primary btn-xs px-2 py-0" style="font-size:.78rem;">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                        </div>
                        @endif
                        
                        @if(!$allApproved && !$isScheduled)
                        <div class="doc-eval-actions">
                            <button type="button" class="btn-eval btn-approve {{ $evalStatusCred === 'approved' ? 'active' : '' }}" data-doc-id="cred-{{ $credential->id }}" onclick="setDocStatus('cred-{{ $credential->id }}', 'approved')">
                                <i class="bi bi-check-circle-fill"></i> Approve
                            </button>
                            <button type="button" class="btn-eval btn-reject {{ $evalStatusCred === 'rejected' ? 'active' : '' }}" data-doc-id="cred-{{ $credential->id }}" onclick="setDocStatus('cred-{{ $credential->id }}', 'rejected')">
                                <i class="bi bi-x-circle-fill"></i> Reject
                            </button>
                        </div>
                        <div class="reject-panel" id="reject-panel-cred-{{ $credential->id }}" style="{{ $evalStatusCred === 'rejected' ? '' : 'display:none;' }}">
                            <label class="reject-remarks-label"><i class="bi bi-pencil-square me-1"></i>Rejection Remarks <span class="text-muted">(optional)</span></label>
                            <textarea class="reject-remarks-input" name="credential_evaluations[{{ $credential->id }}][remarks]" id="remarks-cred-{{ $credential->id }}" placeholder="Explain why this document was rejected…" rows="2">{{ $credential->remarks }}</textarea>
                        </div>
                        @endif
                    </div>
                    @endforeach

                    {{-- Service Agreement --}}
                    @php
                        $evalStatus = in_array($instructor->status, ['approved','rejected']) ? $instructor->status : 'pending';
                        $badgeClass = match($instructor->status) {
                            'approved'     => 'doc-badge-approved',
                            'rejected'     => 'doc-badge-rejected',
                            'for_revision' => 'doc-badge-for_revision',
                            default        => 'doc-badge-pending',
                        };
                        $badgeLabel = match($instructor->status) {
                            'approved'     => 'Approved',
                            'rejected'     => 'Rejected',
                            'for_revision' => 'For Revision',
                            default        => 'Pending',
                        };
                    @endphp
                    <div class="doc-row" id="doc-row-inst-{{ $instructor->id }}">
                        <input type="hidden" name="instructor_evaluations[{{ $instructor->id }}][id]" value="{{ $instructor->id }}">
                        <input type="hidden" name="instructor_evaluations[{{ $instructor->id }}][status]" id="status-input-inst-{{ $instructor->id }}" value="{{ $evalStatus }}">
                        
                        <div class="doc-field-name">
                            <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                            <span class="fw-bold">Service Agreement between FATPro head and instructor</span>
                        </div>
                        <div class="doc-value text-muted" style="font-size:.75rem;">
                            {{ $instructor->service_agreement_path ? basename($instructor->service_agreement_path) : 'No file' }}
                        </div>
                        
                        <span class="doc-badge {{ $badgeClass }}" id="badge-inst-{{ $instructor->id }}">{{ $badgeLabel }}</span>
                        
                        @if($instructor->service_agreement_path)
                        <div class="doc-actions">
                            <a href="{{ route('admin.hcd.instructors.service_agreement.view', $instructor->id) }}?v={{ $instructor->updated_at->timestamp ?? time() }}" target="_blank" class="btn btn-outline-primary btn-xs px-2 py-0" style="font-size:.78rem;">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                        </div>
                        @endif
                        
                        @if(!$allApproved && !$isScheduled)
                        <div class="doc-eval-actions">
                            <button type="button" class="btn-eval btn-approve {{ $evalStatus === 'approved' ? 'active' : '' }}" data-doc-id="inst-{{ $instructor->id }}" onclick="setDocStatus('inst-{{ $instructor->id }}', 'approved')">
                                <i class="bi bi-check-circle-fill"></i> Approve
                            </button>
                            <button type="button" class="btn-eval btn-reject {{ $evalStatus === 'rejected' ? 'active' : '' }}" data-doc-id="inst-{{ $instructor->id }}" onclick="setDocStatus('inst-{{ $instructor->id }}', 'rejected')">
                                <i class="bi bi-x-circle-fill"></i> Reject
                            </button>
                        </div>
                        <div class="reject-panel" id="reject-panel-inst-{{ $instructor->id }}" style="{{ $evalStatus === 'rejected' ? '' : 'display:none;' }}">
                            <label class="reject-remarks-label"><i class="bi bi-pencil-square me-1"></i>Rejection Remarks <span class="text-muted">(optional)</span></label>
                            <textarea class="reject-remarks-input" name="instructor_evaluations[{{ $instructor->id }}][remarks]" id="remarks-inst-{{ $instructor->id }}" placeholder="Explain why this document was rejected…" rows="2">{{ $instructor->remarks }}</textarea>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</form>

{{-- ══ Schedule Interview Button (always visible, enabled only when ALL docs approved) ══ --}}
@if(!$isAccredited && !$isApproved)
<div class="text-center mt-4 mb-3">
    <button type="button"
            id="btn-open-schedule"
            class="btn btn-secondary btn-lg fw-bold px-5 py-3 shadow-sm"
            disabled
            style="border-radius:12px; font-size:1.05rem; letter-spacing:.3px; transition: all .3s ease;">
        <i class="bi bi-hourglass-split me-2 fs-5" id="btn-schedule-icon"></i>
        <span id="btn-schedule-text">Pending Documents</span>
    </button>
    @if($interview)
    <div class="mt-3 d-inline-flex align-items-center gap-3 flex-wrap justify-content-center"
         style="background:#f0faf4;border:1px solid #c3e6cb;border-radius:10px;padding:10px 22px;">
        <span class="text-success fw-semibold small"><i class="bi bi-calendar-event me-1"></i>{{ $interview->interview_date->format('F d, Y') }}</span>
        <span class="text-success fw-semibold small"><i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($interview->interview_time)->format('h:i A') }}</span>
        <span class="text-success fw-semibold small"><i class="bi bi-display me-1"></i>{{ strtoupper($interview->mode) }}</span>
        @if($interview->venue)
        <span class="text-success fw-semibold small"><i class="bi bi-geo-alt me-1"></i>{{ $interview->venue }}</span>
        @endif
    </div>
    @endif
</div>
@endif

{{-- ══ Interview Result Card ══ --}}
@if($interview)
<div class="mt-3 mb-4"
     style="background:#fff;border:1px solid #dee2e6;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06);">

    {{-- Card Header --}}
    <div class="d-flex align-items-center gap-3 px-4 py-3"
         style="background:linear-gradient(135deg,#1a2e5a,#0d1f42);">
        <div style="width:38px;height:38px;background:rgba(255,255,255,.15);border-radius:8px;
                    display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-flag-fill text-white fs-5"></i>
        </div>
        <div>
            <h6 class="text-white mb-0 fw-bold">Interview Result</h6>
            <small class="text-white-50">Record the outcome of the interview session</small>
        </div>
    </div>

    {{-- Card Body --}}
    <div class="px-4 py-4 text-center">

        @if($isAccredited || $isApproved)
        {{-- Already accredited --}}
        <div class="d-inline-flex align-items-center gap-3 px-4 py-3"
             style="background:#d4edda;border:1px solid #c3e6cb;border-radius:10px;">
            <i class="bi bi-patch-check-fill text-success fs-3"></i>
            <div class="text-start">
                <div class="fw-bold text-success" style="font-size:1rem;">Application Approved</div>
                @if($application->accreditation)
                <small class="text-muted">Accreditation No: <strong>{{ $application->accreditation->accreditation_number }}</strong></small><br>
                <small class="text-muted">Valid Until: <strong>{{ $application->accreditation->validity_date->format('F d, Y') }}</strong></small><br>
                <a href="{{ route('admin.hcd.accreditations.certificate', $application->accreditation->id) }}"
                   target="_blank"
                   class="btn btn-success btn-sm mt-2 fw-semibold"
                   style="border-radius:8px;font-size:.82rem;">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Download Certificate PDF
                </a>
                @endif
            </div>
        </div>

        @elseif($isRejected)
        {{-- Already rejected --}}
        <div class="d-inline-flex align-items-center gap-3 px-4 py-3"
             style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:10px;">
            <i class="bi bi-x-circle-fill text-danger fs-3"></i>
            <div class="text-start">
                <div class="fw-bold text-danger" style="font-size:1rem;">Application Rejected</div>
                <small class="text-muted">This application did not pass the interview.</small>
            </div>
        </div>

        @else
        {{-- Awaiting result --}}
        <p class="text-muted mb-4" style="font-size:.9rem;">
            <i class="bi bi-info-circle me-1"></i>
            Select the interview outcome below. This action is <strong>permanent</strong> and will immediately notify the applicant.
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            {{-- PASSED button --}}
            <button type="button"
                    class="btn btn-success btn-lg fw-bold px-5"
                    style="border-radius:10px;min-width:160px;"
                    data-bs-toggle="modal" data-bs-target="#passedConfirmModal">
                <i class="bi bi-check-circle-fill me-2"></i>Passed
            </button>
            {{-- NOT PASSED button --}}
            <button type="button"
                    class="btn btn-danger btn-lg fw-bold px-5"
                    style="border-radius:10px;min-width:160px;"
                    data-bs-toggle="modal" data-bs-target="#notPassedConfirmModal">
                <i class="bi bi-x-circle-fill me-2"></i>Not Passed
            </button>
        </div>
        @endif

    </div>
</div>
@endif

{{-- ══ Schedule Interview Modal ══ --}}
<div class="modal fade" id="scheduleInterviewModal" tabindex="-1"
     aria-labelledby="scheduleInterviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:none; box-shadow:0 20px 60px rgba(0,0,0,.18);">

            {{-- Modal Header --}}
            <div class="modal-header"
                 style="background:linear-gradient(135deg,#1a6fbd,#0d4f9e); border:none; padding:22px 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px;
                                display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-calendar-check-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="scheduleInterviewModalLabel">
                            {{ $interview ? 'Update Interview Schedule' : 'Schedule Interview' }}
                        </h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-4" style="background:#f8fafc;">
                <form id="schedule-interview-form"
                      action="{{ route('admin.hcd.applications.schedule_interview', $application->id) }}"
                      method="POST">
                    @csrf

                        {{-- Notice --}}
                    <div class="mb-3 p-2 d-flex align-items-center gap-2"
                         style="background:#eef5ff;border-radius:8px;border-left:4px solid #1a6fbd;">
                        <i class="bi bi-info-circle-fill text-primary mt-1"></i>
                        <small class="text-muted">
                            An email notification with the interview schedule will be sent to the applicant upon saving.
                        </small>
                    </div>

                    <div class="row g-3">
                        {{-- Date --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#2A3F54;font-size:.85rem;">
                                <i class="bi bi-calendar3 me-1 text-primary"></i>Interview Date
                            </label>
                            <input type="date" name="interview_date" class="form-control"
                                   value="{{ $interview?->interview_date?->format('Y-m-d') }}"
                                   min="{{ now()->format('Y-m-d') }}" required
                                   style="border-radius:8px;border-color:#d0d8e8;">
                        </div>

                        {{-- Time --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#2A3F54;font-size:.85rem;">
                                <i class="bi bi-clock me-1 text-primary"></i>Interview Time
                            </label>
                            <input type="time" name="interview_time" class="form-control"
                                   value="{{ $interview?->interview_time }}" required
                                   style="border-radius:8px;border-color:#d0d8e8;">
                        </div>

                        {{-- Mode --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#2A3F54;font-size:.85rem;">
                                <i class="bi bi-display me-1 text-primary"></i>Interview Mode
                            </label>
                            <select name="mode" id="interview-mode" class="form-select" required
                                    style="border-radius:8px;border-color:#d0d8e8;">
                                <option value="">— Select Mode —</option>
                                <option value="online" {{ ($interview?->mode === 'online') ? 'selected' : '' }}>Online</option>
                                <option value="f2f"    {{ ($interview?->mode === 'f2f')    ? 'selected' : '' }}>Face-to-Face (F2F)</option>
                            </select>
                            <div class="form-text mt-2 d-none" id="online-notice" style="font-size:0.75rem; color:#0d4f9e; padding:6px 10px; background:rgba(13,79,158,.08); border-radius:6px; border-left:3px solid #0d4f9e;">
                                <i class="bi bi-info-circle-fill me-1"></i> If interview is online, a separate email will be sent with the online interview details.
                            </div>
                        </div>

                        {{-- Venue --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#2A3F54;font-size:.85rem;">
                                <i class="bi bi-geo-alt me-1 text-primary"></i>Venue
                                <span class="text-muted fw-normal fst-italic" id="venue-note">(F2F only)</span>
                            </label>
                            <input type="text" name="venue" id="interview-venue" class="form-control"
                                   placeholder="Venue / meeting link"
                                   value="{{ $interview?->venue }}"
                                   style="border-radius:8px;border-color:#d0d8e8;">
                        </div>
                    </div>
                </form>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e4eaf2;padding:16px 28px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>Cancel
                </button>
                <button type="submit" form="schedule-interview-form"
                        class="btn btn-primary fw-bold px-5"
                        style="border-radius:8px; background:linear-gradient(135deg,#1a6fbd,#0d4f9e); border:none;">
                    <i class="bi bi-calendar-check me-2"></i>
                    {{ $interview ? 'Update Schedule' : 'Save Schedule' }}
                </button>
            </div>

        </div>
    </div>
</div>

{{-- ══ Rejection Confirmation Modal ══ --}}
<div class="modal fade" id="rejectionConfirmModal" tabindex="-1"
     aria-labelledby="rejectionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:none; box-shadow:0 20px 60px rgba(0,0,0,.2);">

            {{-- Header --}}
            <div class="modal-header border-0 pb-2"
                 style="background:linear-gradient(135deg,#c0392b,#922b21); padding:22px 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px;
                                display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-envelope-exclamation-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="rejectionConfirmModalLabel">
                            Send Rejection Email
                        </h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            {{-- Body --}}
            <div class="modal-body p-4" style="background:#fafafa;">

                {{-- Applicant info --}}
                <div class="d-flex align-items-center gap-3 mb-3 p-3"
                     style="background:#fff;border-radius:10px;border:1px solid #e4eaf2;">
                    <i class="bi bi-person-circle fs-2 text-danger"></i>
                    <div>
                        <div class="fw-bold" style="color:#2A3F54;font-size:.95rem;">
                            {{ $org?->name ?? ($ind?->full_name ?? 'N/A') }}
                        </div>
                        <small class="text-muted">{{ $org?->email ?? $user->email }}</small>
                    </div>
                </div>

                {{-- Warning notice --}}
                <div class="d-flex align-items-start gap-2 mb-3 p-3"
                     style="background:#fff3cd;border-radius:8px;border-left:4px solid #ffc107;">
                    <i class="bi bi-exclamation-triangle-fill text-warning mt-1"></i>
                    <small class="text-dark">
                        An email will be sent to the applicant listing the rejected documents and remarks. The application status will change to <strong>For Update</strong>.
                    </small>
                </div>

                {{-- Rejected documents list (populated by JS) --}}
                <h6 class="fw-bold mb-2" style="color:#922b21; font-size:.85rem; text-transform:uppercase; letter-spacing:.4px;">
                    <i class="bi bi-x-circle-fill me-1"></i>Rejected Documents
                </h6>
                <div id="rejection-doc-list" class="d-flex flex-column gap-2">
                    {{-- JS fills this dynamically --}}
                </div>
            </div>

            {{-- Footer --}}
            <div class="modal-footer border-0 pt-2" style="background:#fafafa; padding:16px 28px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="bi bi-arrow-left me-1"></i> Go Back
                </button>
                <button type="button" id="btn-confirm-rejection"
                        class="btn btn-danger fw-bold px-5"
                        style="border-radius:8px;">
                    <i class="bi bi-send-fill me-2"></i>Confirm & Send Email
                </button>
            </div>

        </div>
    </div>
</div>

{{-- ══ Passed Confirmation Modal ══ --}}
<div class="modal fade" id="passedConfirmModal" tabindex="-1"
     aria-labelledby="passedConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.18);">

            <div class="modal-header border-0"
                 style="background:linear-gradient(135deg,#1a7a4a,#145c38);padding:22px 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px;
                                display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-patch-check-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="passedConfirmModalLabel">Confirm: Interview Passed</h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4" style="background:#f9fdf9;">
                <div class="d-flex align-items-center gap-3 mb-3 p-3"
                     style="background:#fff;border-radius:10px;border:1px solid #e4eaf2;">
                    <i class="bi bi-person-circle fs-2 text-success"></i>
                    <div>
                        <div class="fw-bold" style="color:#2A3F54;font-size:.95rem;">
                            {{ $org?->name ?? ($ind?->full_name ?? 'N/A') }}
                        </div>
                        <small class="text-muted">{{ $org?->email ?? $user->email }}</small>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-2 p-3 mb-1"
                     style="background:#d4edda;border-radius:8px;border-left:4px solid #28a745;">
                    <i class="bi bi-check-circle-fill text-success mt-1"></i>
                    <small class="text-dark">
                        The application will be marked as <strong>Approved</strong>, an accreditation record will be
                        created with a <strong>3-year validity</strong>, and the applicant will be notified by email.
                    </small>
                </div>
            </div>

            <div class="modal-footer border-0" style="background:#f9fdf9;padding:16px 28px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="bi bi-arrow-left me-1"></i> Cancel
                </button>
                <form method="POST" action="{{ route('admin.hcd.applications.interview_result', $application->id) }}">
                    @csrf
                    <input type="hidden" name="result" value="passed">
                    <button type="submit" class="btn btn-success fw-bold px-5" style="border-radius:8px;">
                        <i class="bi bi-check-circle-fill me-2"></i>Confirm Passed
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ══ Not Passed Confirmation Modal ══ --}}
<div class="modal fade" id="notPassedConfirmModal" tabindex="-1"
     aria-labelledby="notPassedConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <div class="modal-header border-0"
                 style="background:linear-gradient(135deg,#c0392b,#922b21);padding:22px 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.18);border-radius:10px;
                                display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-x-octagon-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-bold" id="notPassedConfirmModalLabel">Confirm: Not Passed</h5>
                        <small class="text-white-50">{{ $application->tracking_number }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4" style="background:#fafafa;">
                <div class="d-flex align-items-center gap-3 mb-3 p-3"
                     style="background:#fff;border-radius:10px;border:1px solid #e4eaf2;">
                    <i class="bi bi-person-circle fs-2 text-danger"></i>
                    <div>
                        <div class="fw-bold" style="color:#2A3F54;font-size:.95rem;">
                            {{ $org?->name ?? ($ind?->full_name ?? 'N/A') }}
                        </div>
                        <small class="text-muted">{{ $org?->email ?? $user->email }}</small>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-2 p-3 mb-1"
                     style="background:#f8d7da;border-radius:8px;border-left:4px solid #dc3545;">
                    <i class="bi bi-exclamation-triangle-fill text-danger mt-1"></i>
                    <small class="text-dark">
                        <strong>Warning — this action is permanent and cannot be undone.</strong><br>
                        The application and all its records (documents, status logs, interview) will be
                        <strong>permanently deleted</strong>. The applicant will be notified by email.
                    </small>
                </div>
            </div>

            <div class="modal-footer border-0" style="background:#fafafa;padding:16px 28px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="bi bi-arrow-left me-1"></i> Cancel
                </button>
                <form method="POST" action="{{ route('admin.hcd.applications.interview_result', $application->id) }}">
                    @csrf
                    <input type="hidden" name="result" value="not_passed">
                    <button type="submit" class="btn btn-danger fw-bold px-5" style="border-radius:8px;">
                        <i class="bi bi-trash-fill me-2"></i>Confirm Not Passed
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    window.ARMS = window.ARMS || {};
    window.ARMS.csrfToken   = '{{ csrf_token() }}';
    window.ARMS.isScheduled = {{ $isScheduled ? 'true' : 'false' }};
    window.ARMS.allApproved = {{ $allApproved ? 'true' : 'false' }};
</script>
<script src="{{ asset('js/evaluation.js') }}?v={{ filemtime(public_path('js/evaluation.js')) }}"></script>
@endpush
