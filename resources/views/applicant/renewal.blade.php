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
            Back
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
    @php
        $renewalStatus = $pendingRenewal->latestStatus?->status?->name ?? 'Submitted';
        $statusColor = match($renewalStatus) {
            'Submitted'               => ['bg' => '#ffc107', 'text' => '#212529', 'icon' => 'fa-paper-plane'],
            'Under Evaluation'        => ['bg' => '#0d6efd', 'text' => '#fff',    'icon' => 'fa-search'],
            'For Update'              => ['bg' => '#dc3545', 'text' => '#fff',    'icon' => 'fa-exclamation-circle'],
            'Scheduled for Interview' => ['bg' => '#0b3d91', 'text' => '#fff',    'icon' => 'fa-calendar-check'],
            'Awaiting Payment'        => ['bg' => '#17a2b8', 'text' => '#fff',    'icon' => 'fa-credit-card'],
            default                   => ['bg' => '#6c757d', 'text' => '#fff',    'icon' => 'fa-circle'],
        };
    @endphp
    <div class="x_panel" style="border-left:4px solid {{ $statusColor['bg'] }};">
        <div class="x_content py-4 text-center">
            <i class="fas fa-info-circle fa-3x text-warning mb-3" style="color: {{ $statusColor['bg'] }} !important;"></i>
            <h5 class="fw-bold">You already have a pending {{ ucfirst($pendingRenewal->application_type) }} application</h5>
            <p class="text-muted mb-1">Tracking Number: <strong>{{ $pendingRenewal->tracking_number }}</strong></p>
            <p class="mb-3">
                <span class="badge px-3 py-2" style="background:{{ $statusColor['bg'] }}; color:{{ $statusColor['text'] }}; font-size:.85rem; border-radius:20px;">
                    <i class="fas {{ $statusColor['icon'] }} me-1"></i>{{ $renewalStatus }}
                </span>
            </p>
            @if($renewalStatus === 'For Update')
                <p class="text-muted mb-0">Some of your documents or credentials require revisions. Please upload the replacements below.</p>
            @elseif($renewalStatus === 'Awaiting Payment')
                <p class="text-muted mb-0">Congratulations! Your interview has passed. Please submit the payment details and signatures below to finalize your accreditation.</p>
            @elseif($renewalStatus === 'Scheduled for Interview' && $pendingRenewal->interview)
                <p class="text-muted mb-3">Your evaluation interview has been scheduled. Please find the details below:</p>
                <div class="d-inline-block text-start p-4 rounded mb-0" style="background: #f8f9fa; border: 1px solid #e9ecef !important; max-width: 600px; width: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.02); border-radius: 8px;">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <small class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Date</small>
                            <span class="text-dark fw-bold" style="font-size: 0.95rem;">
                                <i class="far fa-calendar-alt text-primary me-2"></i>
                                {{ $pendingRenewal->interview->interview_date->format('F d, Y') }}
                            </span>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Time</small>
                            <span class="text-dark fw-bold" style="font-size: 0.95rem;">
                                <i class="far fa-clock text-primary me-2"></i>
                                {{ \Carbon\Carbon::parse($pendingRenewal->interview->interview_time)->format('h:i A') }}
                            </span>
                        </div>
                        <div class="col-sm-6">
                            <small class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Interview Mode</small>
                            <span class="text-dark fw-bold" style="font-size: 0.95rem;">
                                @if($pendingRenewal->interview->mode === 'online')
                                    <i class="fas fa-video text-primary me-2"></i> Online Interview
                                @else
                                    <i class="fas fa-users text-primary me-2"></i> Face-to-Face
                                @endif
                            </span>
                        </div>
                        @if($pendingRenewal->interview->venue)
                            <div class="col-sm-6">
                                <small class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                                    {{ $pendingRenewal->interview->mode === 'online' ? 'Meeting Link' : 'Venue' }}
                                </small>
                                <span class="text-dark fw-bold" style="font-size: 0.95rem; word-break: break-all;">
                                    @if($pendingRenewal->interview->mode === 'online')
                                        <a href="{{ $pendingRenewal->interview->venue }}" target="_blank" class="text-primary text-decoration-none fw-bold">
                                            <i class="fas fa-external-link-alt me-1"></i> Join Meeting
                                        </a>
                                    @else
                                        <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                        {{ $pendingRenewal->interview->venue }}
                                    @endif
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <p class="text-muted mb-0">Finish the application process before submitting another.</p>
            @endif
        </div>
    </div>

    {{-- Submitted Documents & Credentials Summary Panel --}}
    <div class="x_panel mt-4" style="border-left: 4px solid #2a3f54; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); background-color: #fff;">
        <div class="x_title d-flex justify-content-between align-items-center pb-2 border-bottom">
            <h2 class="fw-bold mb-0 text-primary" style="color: #2a3f54 !important; font-size: 1.15rem; border: none; float: none; padding: 0; margin: 0;">
                Submitted Documents & Instructor Credentials
            </h2>
            <button type="button" class="btn btn-sm btn-outline-dark px-3" data-bs-toggle="collapse" data-bs-target="#submittedDocsCollapse" aria-expanded="true" aria-controls="submittedDocsCollapse">
                View Submitted Files
            </button>
            <div class="clearfix"></div>
        </div>
        <div class="x_content collapse show mt-3" id="submittedDocsCollapse">
            <div class="row g-4">
                {{-- FATPro Documents Column --}}
                <div class="col-md-6 border-end">
                    <h6 class="fw-bold text-uppercase text-dark mb-3" style="letter-spacing: 0.5px; font-size: 0.8rem;">
                        1. FATPro General & Legal Documents
                    </h6>
                    @php
                        // Group documents by their document type (section) and sort by section ID
                        $grouped = $pendingRenewal->documents
                            ->sortBy(function ($doc) {
                                $typeId = $doc->documentField?->documentType?->id ?? 999999;
                                $fieldId = $doc->documentField?->id ?? 999999;
                                return sprintf('%08d-%08d', $typeId, $fieldId);
                            })
                            ->groupBy(fn($doc) => optional($doc->documentField?->documentType)->id);

                        $sectionCounter = 1;
                    @endphp

                    <div class="d-flex flex-column gap-3">
                        @forelse($grouped as $typeId => $docs)
                            @php
                                $sectionName = optional($docs->first()?->documentField?->documentType)->name ?? 'Other Documents';
                            @endphp

                            <div class="border rounded shadow-sm mb-3 bg-white" style="border-color: #e5e7eb !important; border-radius: 8px; overflow: hidden;">
                                <div class="px-3 py-2 fw-bold d-flex align-items-center justify-content-between" style="background:#f8fafc; color:#1e293b; font-size:.8rem; border-bottom: 1px solid #e2e8f0;">
                                    <span>{{ $sectionCounter }}. {{ $sectionName }}</span>
                                </div>

                                <div class="list-group list-group-flush">
                                    @foreach($docs as $doc)
                                        @php
                                            $field = $doc->documentField;
                                            $isPdf = $field?->input_type === 'file';
                                            $statusClass = match($doc->status) {
                                                'approved' => 'bg-success text-white',
                                                'rejected', 'returned' => 'bg-danger text-white',
                                                default => 'bg-warning text-dark'
                                            };
                                        @endphp
                                        <div class="list-group-item px-3 py-2 d-flex align-items-center justify-content-between gap-2" style="font-size: 0.82rem; border-bottom: 1px solid #f1f5f9; background: #fff;">
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold text-secondary mb-1">
                                                    {{ $field?->name ?? 'Document' }}
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge {{ $statusClass }}" style="font-size: 0.68rem; padding: 2px 6px; border-radius: 10px;">
                                                        {{ ucfirst($doc->status) }}
                                                    </span>
                                                    @if(!$isPdf && $doc->userDocument?->value)
                                                        <span class="text-muted small" title="{{ $doc->userDocument->value }}" style="font-size: 0.72rem;">
                                                            Value: {{ Str::limit($doc->userDocument->value, 30) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div>
                                                @if($isPdf)
                                                    @if($doc->userDocument?->file_path)
                                                        <a href="{{ route('applicant.documents.view', $doc->id) }}" target="_blank" class="btn btn-xs btn-outline-dark py-1 px-2 fw-semibold" style="font-size: 0.72rem; border-radius: 4px;">
                                                            <i class="fa fa-eye me-1"></i>View
                                                        </a>
                                                    @else
                                                        <span class="text-muted small">No file</span>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @php $sectionCounter++; @endphp
                        @empty
                            <p class="text-muted text-center py-3">No documents submitted.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Instructor Credentials Column --}}
                <div class="col-md-6">
                    <h6 class="fw-bold text-uppercase text-dark mb-3" style="letter-spacing: 0.5px; font-size: 0.8rem;">
                        2. Instructor Credentials & Agreements
                    </h6>
                    
                    @php $appInstructors = $pendingRenewal->user ? $pendingRenewal->user->instructors : collect(); @endphp
                    
                    <div class="d-flex flex-column gap-3">
                        @forelse($appInstructors as $inst)
                            <div class="border rounded shadow-sm bg-white mb-3" style="border-color: #e5e7eb !important; border-radius: 8px; overflow: hidden;">
                                <div class="px-3 py-2 fw-bold d-flex align-items-center justify-content-between" style="background:#f8fafc; color:#1e293b; font-size:.83rem; border-bottom: 1px solid #e2e8f0;">
                                    <span>{{ $inst->first_name }} {{ $inst->last_name }}</span>
                                    @if($inst->service_agreement_path)
                                        <a href="{{ route('applicant.instructors.service_agreement.view', $inst->id) }}" target="_blank" class="btn btn-xs btn-outline-dark py-1 px-2 fw-semibold" style="font-size: 0.72rem; border-radius: 4px;">
                                            <i class="fa fa-file-contract me-1"></i>Agreement
                                        </a>
                                    @else
                                        <span class="text-danger small" style="font-size: 0.72rem;">No Agreement</span>
                                    @endif
                                </div>
                                <div class="list-group list-group-flush">
                                    @foreach($inst->credentials as $cred)
                                        @php
                                            $credStatus = $cred->status ?: 'pending';
                                            $credStatusClass = match($credStatus) {
                                                'approved' => 'badge bg-success text-white',
                                                'rejected', 'returned' => 'badge bg-danger text-white',
                                                default => 'badge bg-warning text-dark'
                                            };
                                        @endphp
                                        <div class="list-group-item px-3 py-2 d-flex align-items-center justify-content-between gap-2" style="font-size: 0.8rem; border-bottom: 1px solid #f1f5f9; background: #fff;">
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold text-secondary mb-1">
                                                    <span class="badge bg-secondary me-2" style="font-size:.65rem; padding: 2px 4px;">{{ $cred->type }}</span>
                                                    Cert #: {{ $cred->number }}
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="{{ $credStatusClass }}" style="font-size: 0.68rem; padding: 2px 6px; border-radius: 10px;">
                                                        {{ ucfirst($credStatus) }}
                                                    </span>
                                                    <span class="text-muted small" style="font-size: 0.72rem;">
                                                        Validity: {{ $cred->validity_date ? \Carbon\Carbon::parse($cred->validity_date)->format('m/d/Y') : 'N/A' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                @if($cred->pdf_path)
                                                    <a href="{{ route('applicant.instructors.credentials.view', $cred->id) }}" target="_blank" class="btn btn-xs btn-outline-dark py-0 px-2 fw-semibold" style="font-size: 0.7rem; border-radius: 4px;">
                                                        <i class="fa fa-eye me-1"></i>View
                                                    </a>
                                                @else
                                                    <span class="text-muted small">No File</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="text-muted text-center py-3 mb-0" style="font-size: 0.85rem;">No instructors submitted.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Batch Resubmission Form (shown only when there are rejected file docs) ── --}}
    @php
        $application = $pendingRenewal;
        $rejectedDocs = $application->documents->filter(
            fn($d) => in_array($d->status, ['rejected','returned'])
        );
        $rejectedInstructors = $application->user ? $application->user->instructors->filter(fn($i) => in_array($i->status, ['rejected','returned'])) : collect();
        $rejectedCredentials = collect();
        if ($application->user) {
            foreach ($application->user->instructors as $inst) {
                foreach ($inst->credentials as $cred) {
                    if (in_array($cred->status, ['rejected','returned'])) {
                        $rejectedCredentials->push($cred);
                    }
                }
            }
        }
        $totalRejected = $rejectedDocs->count() + $rejectedInstructors->count() + $rejectedCredentials->count();
    @endphp
    @if($totalRejected > 0)
    <div class="mt-4 p-4 border rounded-3 text-start" style="background:#fff8f8; border-color:#f5c6cb !important;">
        <h6 class="fw-bold mb-3" style="color:#842029;">
            <i class="bi bi-arrow-repeat me-2"></i>Resubmit Rejected Documents
        </h6>

        <form action="{{ route('applicant.renewal.reupload.store') }}" method="POST" enctype="multipart/form-data" id="batch-resubmit-form" novalidate>
            @csrf
            <input type="hidden" name="application_id" value="{{ $application->id }}">

            {{-- Backend validation error --}}
            @if(session('resubmit_error'))
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-3 py-2" role="alert" style="font-size:.87rem;">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                <span>{{ session('resubmit_error') }}</span>
            </div>
            @endif

            <div class="d-flex flex-column gap-4">
                {{-- FATPro Documents Section --}}
                @if($rejectedDocs->count() > 0)
                @php
                    $groupedRejected = $rejectedDocs
                        ->sortBy(function ($doc) {
                            $typeId = $doc->documentField?->documentType?->id ?? 999999;
                            $fieldId = $doc->documentField?->id ?? 999999;
                            return sprintf('%08d-%08d', $typeId, $fieldId);
                        })
                        ->groupBy(fn($doc) => optional($doc->documentField?->documentType)->id);

                    $resubmitCounter = 1;
                @endphp
                <div>
                    <div class="mb-2 fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">
                        <i class="bi bi-file-earmark-text me-1"></i> FATPro Documents
                    </div>
                    <div class="d-flex flex-column gap-4">
                        @foreach($groupedRejected as $typeId => $docs)
                            @php
                                $sectionName = optional($docs->first()?->documentField?->documentType)->name ?? 'General Requirements';
                            @endphp
                            <div class="border rounded-3 p-3" style="background:#fcfcfc;">
                                <h6 class="fw-bold mb-3" style="color:#0b3d91;">
                                    <i class="bi bi-folder2-open me-2"></i>{{ $resubmitCounter }}. {{ $sectionName }}
                                </h6>
                                <div class="d-flex flex-column gap-3">
                                    @foreach($docs as $rdoc)
                                    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3 p-3 bg-white rounded-2 border shadow-sm">
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold" style="font-size:.9rem; color:#1a2e5a;">
                                                @if($rdoc->documentField?->input_type === 'file')
                                                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                                @else
                                                    <i class="bi bi-input-cursor-text text-danger me-1"></i>
                                                @endif
                                                {{ $rdoc->documentField?->name ?? 'Document' }}
                                            </div>
                                            @if($rdoc->remarks)
                                            <div class="text-muted small mt-1">
                                                <i class="bi bi-chat-left-text me-1"></i>{{ $rdoc->remarks }}
                                            </div>
                                            @endif
                                            @if($rdoc->documentField?->input_type === 'file' && $rdoc->userDocument?->file_path)
                                            <div class="mt-2">
                                                <a href="{{ route('applicant.documents.view', $rdoc->id) }}" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2 fw-semibold" style="font-size: 0.75rem;">
                                                    <i class="bi bi-eye-fill me-1"></i> View Current File
                                                </a>
                                            </div>
                                            @endif
                                        </div>
                                        <div style="min-width:260px;">
                                            @if($rdoc->documentField?->input_type === 'file')
                                                <label class="form-label small fw-semibold mb-1" style="color:#842029;">
                                                    Upload Replacement (PDF) <span class="text-danger">*</span>
                                                </label>
                                                <div class="file-upload-wrapper mt-1">
                                                    <input type="file" name="files[{{ $rdoc->id }}]" id="doc_{{ $rdoc->id }}" class="real-file-input batch-file-input visually-hidden" accept=".pdf" required>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label for="doc_{{ $rdoc->id }}" class="btn btn-outline-danger btn-sm mb-0 px-3 fw-semibold custom-file-btn" style="border-color:#842029; color:#842029;">
                                                            <i class="bi bi-cloud-upload me-1"></i> Choose File
                                                        </label>
                                                        <span class="file-name-text text-muted text-truncate" style="font-size: .8rem; max-width: 200px;">No file chosen</span>
                                                    </div>
                                                    <div class="invalid-feedback file-invalid-feedback" style="font-size: 0.8rem; margin-top: 4px;">Please select a valid PDF file.</div>
                                                </div>
                                                <div class="text-muted" style="font-size:.72rem; margin-top:6px;">Max 10MB · PDF only</div>
                                            @else
                                                <label class="form-label small fw-semibold mb-1" style="color:#842029;">
                                                    Update Value <span class="text-danger">*</span>
                                                </label>
                                                <input type="{{ $rdoc->documentField->input_type === 'date' ? 'date' : 'text' }}" 
                                                       name="values[{{ $rdoc->id }}]" 
                                                       id="doc_{{ $rdoc->id }}" 
                                                       class="form-control form-control-sm" 
                                                       value="{{ $rdoc->userDocument?->value }}" 
                                                       required>
                                            @endif
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @php $resubmitCounter++; @endphp
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Instructor Credentials Section --}}
                @if($rejectedInstructors->count() > 0 || $rejectedCredentials->count() > 0)
                <div>
                    <div class="mb-2 fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">
                        <i class="bi bi-person-badge me-1"></i> Instructor Credentials
                    </div>
                    <div class="d-flex flex-column gap-3">
                        {{-- Instructor Service Agreements --}}
                        @foreach($rejectedInstructors as $rInst)
                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3 p-3 bg-white rounded-2 border shadow-sm">
                            <div class="flex-grow-1">
                                <div class="fw-semibold" style="font-size:.9rem; color:#1a2e5a;">
                                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                    Service Agreement - {{ $rInst->first_name }} {{ $rInst->last_name }}
                                </div>
                                @if($rInst->remarks)
                                <div class="text-muted small mt-1">
                                    <i class="bi bi-chat-left-text me-1"></i>{{ $rInst->remarks }}
                                </div>
                                @endif
                                @if($rInst->service_agreement_path)
                                <div class="mt-2">
                                    <a href="{{ route('applicant.instructors.service_agreement.view', $rInst->id) }}" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2 fw-semibold" style="font-size: 0.75rem;">
                                        <i class="bi bi-eye-fill me-1"></i> View Current File
                                    </a>
                                </div>
                                @endif
                            </div>
                            <div style="min-width:260px;">
                                <label class="form-label small fw-semibold mb-1" style="color:#842029;">
                                    Upload Replacement (PDF) <span class="text-danger">*</span>
                                </label>
                                <div class="file-upload-wrapper mt-1">
                                    <input type="file" name="instructor_files[{{ $rInst->id }}]" id="inst_{{ $rInst->id }}" class="real-file-input batch-file-input visually-hidden" accept=".pdf" required>
                                    <div class="d-flex align-items-center gap-2">
                                        <label for="inst_{{ $rInst->id }}" class="btn btn-outline-danger btn-sm mb-0 px-3 fw-semibold custom-file-btn" style="border-color:#842029; color:#842029;">
                                            <i class="bi bi-cloud-upload me-1"></i> Choose File
                                        </label>
                                        <span class="file-name-text text-muted text-truncate" style="font-size: .8rem; max-width: 200px;">No file chosen</span>
                                    </div>
                                    <div class="invalid-feedback file-invalid-feedback" style="font-size: 0.8rem; margin-top: 4px;">Please select a valid PDF file.</div>
                                </div>
                                <div class="text-muted" style="font-size:.72rem; margin-top:6px;">Max 10MB · PDF only</div>
                            </div>
                        </div>
                        @endforeach

                        {{-- Instructor Credentials --}}
                        @foreach($rejectedCredentials as $rCred)
                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3 p-3 bg-white rounded-2 border shadow-sm">
                            <div class="flex-grow-1">
                                <div class="fw-semibold" style="font-size:.9rem; color:#1a2e5a;">
                                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                    {{ $rCred->type }} Credential - {{ $rCred->instructor->first_name }} {{ $rCred->instructor->last_name }}
                                </div>
                                @if($rCred->remarks)
                                <div class="text-muted small mt-1">
                                    <i class="bi bi-chat-left-text me-1"></i>{{ $rCred->remarks }}
                                </div>
                                @endif
                                @if($rCred->pdf_path)
                                <div class="mt-2">
                                    <a href="{{ route('applicant.instructors.credentials.view', $rCred->id) }}" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2 fw-semibold" style="font-size: 0.75rem;">
                                        <i class="bi bi-eye-fill me-1"></i> View Current File
                                    </a>
                                </div>
                                @endif
                            </div>
                            <div style="min-width:260px;">
                                <label class="form-label small fw-semibold mb-1" style="color:#842029;">
                                    Upload Replacement (PDF) <span class="text-danger">*</span>
                                </label>
                                <div class="file-upload-wrapper mt-1">
                                    <input type="file" name="credential_files[{{ $rCred->id }}]" id="cred_{{ $rCred->id }}" class="real-file-input batch-file-input visually-hidden" accept=".pdf" required>
                                    <div class="d-flex align-items-center gap-2">
                                        <label for="cred_{{ $rCred->id }}" class="btn btn-outline-danger btn-sm mb-0 px-3 fw-semibold custom-file-btn" style="border-color:#842029; color:#842029;">
                                            <i class="bi bi-cloud-upload me-1"></i> Choose File
                                        </label>
                                        <span class="file-name-text text-muted text-truncate" style="font-size: .8rem; max-width: 200px;">No file chosen</span>
                                    </div>
                                    <div class="invalid-feedback file-invalid-feedback" style="font-size: 0.8rem; margin-top: 4px;">Please select a valid PDF file.</div>
                                </div>
                                <div class="text-muted" style="font-size:.72rem; margin-top:6px;">Max 10MB · PDF only</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <div class="mt-4" id="batch-form-error-banner" style="display:none;" role="alert" aria-live="assertive">
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-3 py-2" style="font-size:.87rem;">
                    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                    <span>Please upload a replacement PDF for <strong>every</strong> rejected item before submitting.</span>
                </div>
            </div>

            <div class="mt-3 text-end">
                <button type="button" class="btn btn-danger fw-bold px-5" id="btn-resubmit-all"
                        onclick="handleResubmitSubmit(event)">
                    <i class="bi bi-send-fill me-2"></i>
                    Resubmit All Documents ({{ $totalRejected }})
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- ── Payment Requirements Upload Section ── --}}
    @if($renewalStatus === 'Awaiting Payment' || ($pendingRenewal && $pendingRenewal->payment))
    @php
        $payment = $pendingRenewal->payment;
        $reqs = [
            'proof_of_payment' => [
                'label' => 'Proof of Payment',
                'desc' => 'Attach clear copy of your payment receipt or deposit slip (PDF/JPG/PNG)',
                'accept' => '.pdf,.jpg,.jpeg,.png'
            ],
        ];
        $needsUpload = false;
    @endphp

    <div class="x_panel mt-4">
        <div class="x_title">
            <h2 class="fw-bold">Submit Proof of Payment</h2>
            <div class="clearfix"></div>
        </div>
        <div class="x_content py-3">
            <div class="alert alert-warning border border-warning-subtle d-flex align-items-start gap-3 p-3 mb-3 bg-white" style="border-radius: 10px; color: #856404;">
                <i class="fas fa-info-circle fs-5 mt-1 text-warning"></i>
                <div>
                    <h5 class="fw-bold mb-1" style="color: #856404; font-size: 0.95rem;">Accreditation Fees Breakdown</h5>
                    <p class="mb-2 text-muted small" style="font-size: 0.85rem;">Please pay a total of <strong>PHP 900.00</strong> to complete your accreditation process. The breakdown is as follows:</p>
                    <ul class="mb-0 small text-muted pl-3" style="font-size: 0.85rem; list-style-type: disc;">
                        <li>Application Fee: <strong>PHP 600.00</strong></li>
                        <li>Accreditation Certification Fee: <strong>PHP 300.00</strong></li>
                    </ul>
                </div>
            </div>
            <p class="text-muted mb-4" style="font-size: 0.95rem;">
                Congratulations on passing your interview! To proceed with issuing your renewal/reinstatement accreditation certificate, please upload your Proof of Payment below.
            </p>

            <form action="{{ route('applicant.renewal.submit_payment') }}" method="POST" enctype="multipart/form-data" id="payment-upload-form" novalidate>
                @csrf
                <input type="hidden" name="application_id" value="{{ $pendingRenewal->id }}">

                <div class="d-flex flex-column gap-3 mb-4">
                    @foreach($reqs as $key => $info)
                        @php
                            $filePath = $payment ? $payment->$key : null;
                            $status = $payment ? $payment->{"{$key}_status"} : 'missing';
                            if (!$filePath) {
                                $status = 'missing';
                            }
                            $remarks = $payment ? $payment->{"{$key}_remarks"} : '';
                        @endphp
                        <div class="p-3 border rounded shadow-sm" style="background: #fafafa; border-color: #e5e5e5 !important;">
                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <h5 class="fw-bold mb-1" style="color: #2A3F54; font-size: 1rem;">{{ $info['label'] }}</h5>
                                    <p class="text-muted mb-2" style="font-size: 0.85rem;">{{ $info['desc'] }}</p>

                                    <div class="d-flex align-items-center gap-2">
                                        @if($status === 'approved')
                                            <span class="badge bg-success" style="font-size: 0.8rem; padding: 5px 10px;">
                                                <i class="fas fa-check-circle me-1"></i> Approved
                                            </span>
                                        @elseif($status === 'rejected')
                                            <span class="badge bg-danger" style="font-size: 0.8rem; padding: 5px 10px;">
                                                <i class="fas fa-times-circle me-1"></i> Requires Revision
                                            </span>
                                        @elseif($status === 'pending')
                                            <span class="badge bg-warning text-dark" style="font-size: 0.8rem; padding: 5px 10px;">
                                                <i class="fas fa-hourglass-half me-1"></i> Pending Review
                                            </span>
                                        @else
                                            <span class="badge bg-secondary" style="font-size: 0.8rem; padding: 5px 10px;">
                                                <i class="fas fa-arrow-up me-1"></i> Not Uploaded
                                            </span>
                                        @endif

                                        @if($filePath)
                                            <span class="text-muted small ms-2" style="font-size: 0.8rem;">
                                                <i class="fas fa-file-alt me-1"></i> {{ basename($filePath) }}
                                            </span>
                                        @endif
                                    </div>

                                    @if($status === 'rejected' && $remarks)
                                        <div class="alert alert-danger py-1 px-3 border-0 rounded mt-3 mb-0" style="font-size: 0.85rem;">
                                            <strong>Verifier Remarks:</strong> {{ $remarks }}
                                        </div>
                                    @endif
                                </div>

                                <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                    @if(($status === 'missing' || $status === 'rejected') && $renewalStatus === 'Awaiting Payment')
                                        @php $needsUpload = true; @endphp
                                        <div class="file-upload-wrapper mt-1">
                                            <input type="file" name="{{ $key }}" id="input-{{ $key }}" class="real-file-input payment-file-input visually-hidden" accept="{{ $info['accept'] }}" required>
                                            <div class="d-flex align-items-center justify-content-md-end gap-2">
                                                <label for="input-{{ $key }}" class="btn btn-outline-info btn-sm mb-0 px-3 fw-bold custom-file-btn" style="border-color: #17a2b8; color: #17a2b8;">
                                                    <i class="fas fa-cloud-upload-alt me-1"></i> Choose File
                                                </label>
                                                <span class="file-name-text text-muted text-truncate text-start" style="font-size: .8rem; max-width: 180px;">No file chosen</span>
                                            </div>
                                        </div>
                                    @else
                                        @if($status === 'approved' || $status === 'pending')
                                            <span class="text-success fw-bold small"><i class="fas fa-check me-1"></i> Under Review / Complete</span>
                                        @else
                                            <span class="text-muted fw-bold small"><i class="fas fa-ban me-1"></i> Not Available / Locked</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($needsUpload)
                    <div class="text-end">
                        <button type="submit" class="btn btn-info fw-bold px-5" style="border-radius: 8px; background-color: #17a2b8; border-color: #17a2b8; color: white;">
                            <i class="fas fa-cloud-upload-alt me-2"></i> Upload Proof of Payment
                        </button>
                    </div>
                @else
                    <div class="alert alert-success mb-0 text-center fw-semibold py-3">
                        <i class="fas fa-check-double me-2"></i> Proof of Payment has been uploaded successfully. The HCD verifier is currently reviewing your payment details.
                    </div>
                @endif
            </form>
        </div>
    </div>
    @endif
    @elseif($pendingInstructorUpdate)

    {{-- Guard: pending instructor update --}}
    <div class="x_panel" style="border-left:4px solid #f39c12;">
        <div class="x_content py-4 text-center">
            <i class="fas fa-user-edit fa-3x text-warning mb-3"></i>
            <h5 class="fw-bold">You have a pending instructor update request</h5>
            <p class="text-muted mb-3">You cannot file for renewal or reinstatement until your instructor's credentials update is completed.</p>
            <a href="{{ route('applicant.instructors.show', $pendingInstructorUpdate->id) }}" class="btn btn-warning fw-bold px-4" style="border-radius:20px; color:#fff;">
                <i class="fas fa-arrow-right me-1"></i> View Update Request
            </a>
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
                <div class="col-md mb-2 border-end">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Accreditation Number</p>
                    <p class="fw-bold fs-5 mb-0" style="color:#0b3d91;">{{ $accreditation->accreditation_number ?? 'N/A' }}</p>
                </div>
                <div class="col-md mb-2 border-end">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Date Accredited</p>
                    <p class="fw-bold fs-5 mb-0" style="color:#2A3F54;">{{ $accreditation->date_of_accreditation ? \Carbon\Carbon::parse($accreditation->date_of_accreditation)->format('F d, Y') : 'N/A' }}</p>
                </div>
                <div class="col-md mb-2 border-end">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Validity Period</p>
                    <p class="fw-bold fs-5 mb-0" style="color:#2A3F54;">{{ $accreditation->validity_date ? \Carbon\Carbon::parse($accreditation->validity_date)->format('F d, Y') : 'N/A' }}</p>
                </div>
                @if($accreditation->status === 'revoked')
                <div class="col-md mb-2 border-end">
                    <p class="text-muted mb-1" style="font-size:.85rem;text-transform:uppercase;">Revoked Date</p>
                    <p class="fw-bold fs-5 mb-0 text-danger">{{ $accreditation->updated_at ? $accreditation->updated_at->format('F d, Y') : 'N/A' }}</p>
                </div>
                @endif
                <div class="col-md">
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
    <form action="{{ route('applicant.renewal.store') }}" method="POST" enctype="multipart/form-data" id="renewalForm" novalidate>
        @csrf

        {{-- Step 1 — Application Type --}}
        <div class="x_panel">
            <div class="x_title"><h2><i class="fas fa-exchange-alt me-2"></i>Application Type</h2><div class="clearfix"></div></div>
            <div class="x_content">
                <div class="row g-3">
                    <div class="col-md-12">
                        <div class="form-check form-check-inline"
                             @if($accreditation->status === 'revoked')
                                 data-bs-toggle="tooltip" 
                                 data-bs-placement="top" 
                                 title="Renewal is disabled because your current accreditation is revoked. Please use Reinstatement instead."
                             @endif>
                            <input class="form-check-input" type="radio" name="application_type" id="type_renewal" value="renewal" required 
                                {{ $accreditation->status === 'revoked' ? 'disabled' : 'checked' }}
                                style="{{ $accreditation->status === 'revoked' ? 'cursor: not-allowed;' : '' }}">
                            <label class="form-check-label fw-semibold {{ $accreditation->status === 'revoked' ? 'text-muted text-decoration-line-through' : '' }}" 
                                   for="type_renewal"
                                   style="{{ $accreditation->status === 'revoked' ? 'cursor: not-allowed;' : '' }}">
                                <i class="fas fa-sync-alt text-info me-1"></i> Renewal
                            </label>
                        </div>
                        <div class="form-check form-check-inline ms-4"
                             @if($accreditation->status !== 'revoked')
                                 data-bs-toggle="tooltip" 
                                 data-bs-placement="top" 
                                 title="Reinstatement is disabled because your current accreditation is active or expired. Please use Renewal instead."
                             @endif>
                            <input class="form-check-input" type="radio" name="application_type" id="type_reinstatement" value="reinstatement"
                                {{ $accreditation->status !== 'revoked' ? 'disabled' : 'checked' }}
                                style="{{ $accreditation->status !== 'revoked' ? 'cursor: not-allowed;' : '' }}">
                            <label class="form-check-label fw-semibold {{ $accreditation->status !== 'revoked' ? 'text-muted text-decoration-line-through' : '' }}" 
                                   for="type_reinstatement"
                                   style="{{ $accreditation->status !== 'revoked' ? 'cursor: not-allowed;' : '' }}">
                                <i class="fas fa-redo text-secondary me-1"></i> Reinstatement
                            </label>
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
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Telephone</label>
                        <input type="text" class="form-control" id="telephone" name="telephone" value="{{ old('telephone', preg_replace('/[^0-9]/', '', $org->telephone)) }}" placeholder="e.g. 0281234567" pattern="[0-9]{10}" maxlength="10">
                        <div class="invalid-feedback">Enter a valid 10-digit telephone number (e.g. 0281234567).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Fax</label>
                        <input type="text" class="form-control" id="fax" name="fax" value="{{ old('fax', preg_replace('/[^0-9]/', '', $org->fax)) }}" placeholder="e.g. 0281234567" pattern="[0-9]{10}" maxlength="10">
                        <div class="invalid-feedback">Enter a valid 10-digit facsimile number (e.g. 0281234567).</div>
                    </div>
                    <div class="col-md-12"><label class="form-label fw-semibold">Organization Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="org_email" value="{{ old('org_email', $org->email) }}" required></div>
                </div>

                <hr>
                <h6 class="fw-bold mb-3"><i class="fas fa-user-tie me-2"></i>Authorized Representative</h6>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="rep_full_name" value="{{ old('rep_full_name', $rep?->full_name) }}" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Position <span class="text-danger">*</span></label><input type="text" class="form-control" name="rep_position" value="{{ old('rep_position', $rep?->position) }}" required></div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rep_contact" name="rep_contact_number" value="{{ old('rep_contact_number', $rep?->contact_number) }}" required pattern="^(09|\+639)\d{9}$" maxlength="13">
                        <div class="invalid-feedback">Enter a valid PH mobile number (e.g. 09171234567).</div>
                    </div>
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
                        <input type="hidden" name="instructors[{{ $idx }}][id]" value="{{ $inst->id }}">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold mb-0" style="color:#0b3d91;"><i class="fas fa-user me-2"></i><span class="instructor-label">Instructor #{{ $idx + 1 }}</span></h6>
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
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Certificate Number <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[{{ $idx }}][credentials][{{ $type }}][number]" value="{{ $cred?->number }}" required></div>
                                @if($type !== 'BOSH')
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Issued Date <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="instructors[{{ $idx }}][credentials][{{ $type }}][issued_date]" value="{{ $cred?->issued_date }}" required></div>
                                @endif
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Validity Date <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="instructors[{{ $idx }}][credentials][{{ $type }}][validity_date]" value="{{ $cred?->validity_date }}" required></div>
                                @if($type === 'BOSH')
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Training Dates <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[{{ $idx }}][credentials][{{ $type }}][training_dates]" value="{{ $cred?->training_dates }}" required></div>
                                @endif
                                <div class="col-12"><label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF <span class="text-danger">*</span> @if($cred?->pdf_path)<span class="text-success">(current: {{ basename($cred->pdf_path) }})</span> <a href="{{ route('applicant.instructors.credentials.view', $cred->id) }}" target="_blank" class="btn btn-xs btn-outline-dark py-0 px-2 fw-semibold ms-2" style="font-size: 0.7rem;"><i class="fas fa-eye me-1"></i>View</a>@endif</label><input type="file" class="form-control form-control-sm" name="instructors[{{ $idx }}][credentials][{{ $type }}][pdf]" accept=".pdf" required></div>
                            </div>
                        </div>
                        @endforeach

                        <div class="border rounded-2 p-3" style="background:#fffdf4;border-color:#d4ac4b !important;">
                            <p class="fw-bold mb-2" style="font-size:.83rem;color:#7a5c00;"><i class="fas fa-file-contract me-1"></i>Service Agreement <span class="text-danger">*</span> @if($inst->service_agreement_path)<span class="text-success">(current: {{ basename($inst->service_agreement_path) }})</span> <a href="{{ route('applicant.instructors.service_agreement.view', $inst->id) }}" target="_blank" class="btn btn-xs btn-outline-dark py-0 px-2 fw-semibold ms-2" style="font-size: 0.7rem;"><i class="fas fa-eye me-1"></i>View</a>@endif</p>
                            <input type="file" class="form-control form-control-sm" name="instructors[{{ $idx }}][service_agreement]" accept=".pdf" required>
                        </div>
                    </div>
                    @endforeach

                    @if($instructors->isEmpty())
                    <div class="instructor-card border rounded-3 bg-white shadow-sm p-3 mb-3" data-idx="0">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold mb-0" style="color:#0b3d91;"><i class="fas fa-user me-2"></i><span class="instructor-label">Instructor #1</span></h6>
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
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Certificate Number <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[0][credentials][{{ $type }}][number]" required></div>
                                @if($type !== 'BOSH')<div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Issued Date <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="instructors[0][credentials][{{ $type }}][issued_date]" required></div>@endif
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Validity Date <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="instructors[0][credentials][{{ $type }}][validity_date]" required></div>
                                @if($type === 'BOSH')<div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Training Dates <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[0][credentials][{{ $type }}][training_dates]" required></div>@endif
                                <div class="col-12"><label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF <span class="text-danger">*</span></label><input type="file" class="form-control form-control-sm" name="instructors[0][credentials][{{ $type }}][pdf]" accept=".pdf" required></div>
                            </div>
                        </div>
                        @endforeach
                        <div class="border rounded-2 p-3" style="background:#fffdf4;border-color:#d4ac4b !important;">
                            <p class="fw-bold mb-2" style="font-size:.83rem;color:#7a5c00;"><i class="fas fa-file-contract me-1"></i>Service Agreement <span class="text-danger">*</span></p>
                            <input type="file" class="form-control form-control-sm" name="instructors[0][service_agreement]" accept=".pdf" required>
                        </div>
                    </div>
                    @endif
                </div>

                <div class="d-flex justify-content-end mt-3 mb-2">
                    <button type="button" id="addInstructorBtn" class="btn btn-outline-primary btn-sm fw-semibold px-4" style="border-radius:8px;">
                        <i class="fas fa-plus-circle me-1"></i>Add Instructor
                    </button>
                </div>

                <template id="instructorTemplate" class="d-none" aria-hidden="true">
                    <div class="instructor-card border rounded-3 bg-white shadow-sm p-3 mb-3">
                        <input type="hidden" name="instructors[__IDX__][id]" value="">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold mb-0" style="color:#0b3d91;"><i class="fas fa-user me-2"></i><span class="instructor-label">Instructor #__IDX_DISPLAY__</span></h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-instructor-btn"><i class="fas fa-trash me-1"></i>Remove</button>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[__IDX__][first_name]" required></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Middle Name</label><input type="text" class="form-control form-control-sm" name="instructors[__IDX__][middle_name]"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[__IDX__][last_name]" required></div>
                        </div>
                        @foreach(['EMS' => 'TESDA EMS NC II/III', 'TM1' => 'TESDA TM1', 'NTTC' => 'TESDA NTTC', 'BOSH' => 'BOSH SO1/SO2'] as $type => $label)
                        <div class="border rounded-2 p-3 mb-2" style="background:#f8f9ff;">
                            <p class="fw-bold mb-2" style="font-size:.83rem;color:#0b3d91;"><span class="badge me-1" style="background:#0b3d91;font-size:.7rem;">{{ $type }}</span>{{ $label }}</p>
                            <div class="row g-2">
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Certificate Number <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][{{ $type }}][number]" required></div>
                                @if($type !== 'BOSH')<div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Issued Date <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][{{ $type }}][issued_date]" required></div>@endif
                                <div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Validity Date <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="instructors[__IDX__][credentials][{{ $type }}][validity_date]" required></div>
                                @if($type === 'BOSH')<div class="col-md-4"><label class="form-label mb-1" style="font-size:.8rem;">Training Dates <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="instructors[__IDX__][credentials][{{ $type }}][training_dates]" required></div>@endif
                                <div class="col-12"><label class="form-label mb-1" style="font-size:.8rem;">Certificate PDF <span class="text-danger">*</span></label><input type="file" class="form-control form-control-sm" name="instructors[__IDX__][credentials][{{ $type }}][pdf]" accept=".pdf" required></div>
                            </div>
                        </div>
                        @endforeach
                        <div class="border rounded-2 p-3" style="background:#fffdf4;border-color:#d4ac4b !important;">
                            <p class="fw-bold mb-2" style="font-size:.83rem;color:#7a5c00;"><i class="fas fa-file-contract me-1"></i>Service Agreement <span class="text-danger">*</span></p>
                            <input type="file" class="form-control form-control-sm" name="instructors[__IDX__][service_agreement]" accept=".pdf" required>
                        </div>
                    </div>
                </template>
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
                        ['code'=>'LEGAL_01','title'=>'DOLE Registration','label'=>'Certificate of Registration to the Department of Labor and Employment (Rule 10-20, OSHS).','required'=>true],
                        ['code'=>'LEGAL_02','title'=>'Business Registration','label'=>'Registration of business with DTI, SEC, or CDA.','required'=>true],
                        ['code'=>'LEGAL_03','title'=>'Articles of Incorporation','label'=>'Articles of Incorporation with By-Laws','required'=>true],
                        ['code'=>'LEGAL_04','title'=>"Mayor's Permit",'label'=>"Valid Mayor's Permit",'required'=>true],
                        ['code'=>'LEGAL_05','title'=>'BIR Registration & TIN','label'=>'Registration Certificate with BIR, TIN, receipts, and Books of Accounts','required'=>true],
                        ['code'=>'LEGAL_06','title'=>'DOLE clearance','label'=>'DOLE-issued certificate of no pending labor standard case','required'=>true],
                        ['code'=>'LEGAL_07','title'=>'Lease/Ownership Agreement','label'=>'Lease agreement or evidence of ownership of building','required'=>false],
                    ]],
                    ['title' => 'Training Management and Staff', 'badge' => '2', 'docs' => [
                        ['code'=>'TRAIN_01','title'=>'Organizational Chart','label'=>'Chart showing management, teaching and support staff','required'=>true],
                        ['code'=>'TRAIN_02','title'=>'TESDA Certificate','label'=>'For TVIs: EMS NC II Program Registration from TESDA (if applicable)','required'=>false],
                        ['code'=>'TRAIN_03','title'=>'Training Monitoring','label'=>'Monitoring of delivery of training program plan','required'=>true],
                    ]],
                    ['title' => 'Premises Including Occupational Safety', 'badge' => '3', 'docs' => [
                        ['code'=>'PREM_01','title'=>'Location Map','label'=>"Organization's location map",'required'=>true],
                        ['code'=>'PREM_02','title'=>'Site Floor Plan','label'=>'Detailed floor plan including classrooms, facilities, and emergency exits.','required'=>true],
                        ['code'=>'PREM_03','title'=>'OSH Policy & Program','label'=>'Occupational Safety and Health Policy and Program','required'=>true],
                        ['code'=>'PREM_04','title'=>'Decontamination Procedures','label'=>'Written procedures for decontamination of first aid tools/equipment.','required'=>true],
                        ['code'=>'PREM_05','title'=>'Safety Officers List','label'=>'List of qualified and designated "safety officers".','required'=>true],
                        ['code'=>'PREM_06','title'=>'First-Aiders List','label'=>'List of qualified first-aiders in the organization.','required'=>true],
                        ['code'=>'PREM_07','title'=>'First-Aider Certificate','label'=>'Valid first-aider certificate in your organization.','required'=>true],
                    ]],
                    ['title' => 'Policies on IP and Data Protection', 'badge' => '4', 'docs' => [
                        ['code'=>'IP_01','title'=>'Data Privacy Policy','label'=>'Written policy on how to ensure privacy and security of the data subjects.','required'=>true],
                        ['code'=>'IP_02','title'=>'Intellectual Property Policy','label'=>'Written policy on use of intellectual properties as applicable.','required'=>true],
                    ]],
                    ['title' => 'Quality Assurance and Enhancement', 'badge' => '5', 'docs' => [
                        ['code'=>'QA_01','title'=>'Course Review Procedures','label'=>'Written procedures for conducting training course review, including programs and names of trainers.','required'=>false],
                        ['code'=>'QA_02','title'=>'Test Results Summary','label'=>'Template summary of the pre- and post-test results.','required'=>true],
                        ['code'=>'QA_03','title'=>'Evaluation Summary','label'=>'Template summary of general and individual trainer evaluation numerical ratings.','required'=>true],
                        ['code'=>'QA_04','title'=>'Assessment Tools','label'=>'Sample assessment tools such as test questions, etc.','required'=>true],
                        ['code'=>'QA_05','title'=>'Participant Directory Template','label'=>'Template containing participant data, unique codes, and ID pictures.','required'=>true],
                        ['code'=>'QA_06','title'=>'Attendance Sheet Template','label'=>'Daily attendance sheet template.','required'=>true],
                        ['code'=>'QA_07','title'=>'Emergency First Aid Manual','label'=>'Emergency First Aid (1-day) Manual.','required'=>true],
                        ['code'=>'QA_08','title'=>'Occupational First Aid Manual','label'=>'Occupational First Aid (2-days) Manual.','required'=>true],
                        ['code'=>'QA_09','title'=>'Standard First Aid Manual','label'=>'Standard First Aid (4-days) Manual.','required'=>true],
                    ]],
                    ['title' => 'Training Equipment and Materials', 'badge' => '6', 'docs' => [
                        ['code'=>'EQUIP_01','title'=>'Equipment & Materials List','label'=>'Unified document with photos of First-Aid materials, general equipment, and participant kits (Refer to FATPro MOP).','required'=>true],
                    ]],
                ];
                @endphp

                @foreach($docSections as $section)
                <div class="border rounded-3 bg-white shadow-sm p-3 mb-3">
                    <h6 class="fw-bold mb-3" style="color:#0b3d91;"><span class="badge me-2" style="background:#0b3d91;">{{ $section['badge'] }}</span>{{ $section['title'] }}</h6>
                    <div class="row g-3">
                        @if($section['badge'] == '4')
                        <div class="col-md-12 mb-2">
                            <label class="form-label fw-bold mb-1" style="font-size:.88rem;">Data Protection Officer Name <span class="text-danger">*</span></label>
                            <div class="form-text mt-0 mb-2" style="font-size:.75rem; line-height: 1.2; color: #6c757d;">Please provide the full name of your designated Data Protection Officer.</div>
                            @php $existingDPO = $existingDocs->get('IP_DPO_NAME'); @endphp
                            @if($existingDPO && $existingDPO->value)
                                <div class="form-text mt-0 mb-1" style="font-size:.75rem;color:#198754;"><i class="fas fa-check-circle me-1"></i>Current: {{ Str::limit($existingDPO->value, 30) }}</div>
                            @endif
                            <input type="text" class="form-control form-control-sm" name="documents[IP_DPO_NAME]" value="{{ $existingDPO?->value }}" placeholder="Full name of DPO" required>
                        </div>
                        @endif

                        @foreach($section['docs'] as $f)
                        @php $existing = $existingDocs->get($f['code']); @endphp
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-bold mb-1" style="font-size:.88rem;">{{ $f['title'] }} @if($f['required']) <span class="text-danger">*</span> @endif</label>
                            <div class="form-text mt-0 mb-2" style="font-size:.75rem; line-height: 1.2; color: #6c757d;">{{ $f['label'] }}</div>
                            @if($existing && $existing->file_path)
                                <div class="form-text mt-0 mb-1" style="font-size:.75rem;color:#198754;"><i class="fas fa-check-circle me-1"></i>Current: {{ basename($existing->file_path) }} <a href="{{ route('applicant.user_documents.view', $existing->id) }}" target="_blank" class="btn btn-xs btn-outline-dark py-0 px-2 fw-semibold ms-2" style="font-size: 0.7rem;"><i class="fas fa-eye me-1"></i>View</a></div>
                            @elseif($existing && $existing->value)
                                <div class="form-text mt-0 mb-1" style="font-size:.75rem;color:#198754;"><i class="fas fa-check-circle me-1"></i>Value: {{ Str::limit($existing->value, 30) }}</div>
                            @endif
                            <input type="file" class="form-control form-control-sm" name="documents[{{ $f['code'] }}]" accept=".pdf" @if($f['required'] && !$existing) required @endif>
                        </div>
                        @endforeach

                        @if($section['badge'] == '3')
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-bold mb-1" style="font-size:.88rem;">Certificate Validity Date <span class="text-danger">*</span></label>
                            <div class="form-text mt-0 mb-2" style="font-size:.75rem; line-height: 1.2; color: #6c757d;">Validity date of your first-aider certificate.</div>
                            @php $existingPremDate = $existingDocs->get('PREM_DATE'); @endphp
                            @if($existingPremDate && $existingPremDate->value)
                                <div class="form-text mt-0 mb-1" style="font-size:.75rem;color:#198754;"><i class="fas fa-check-circle me-1"></i>Current: {{ $existingPremDate->value }}</div>
                            @endif
                            <input type="date" class="form-control form-control-sm" name="documents[PREM_DATE]" value="{{ $existingPremDate?->value }}" required>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach

                {{-- Special fields: PREM_DATE and IP_DPO_NAME --}}
            </div>
        </div>

        <div class="text-center py-4 mb-4">
            <button type="submit" class="btn btn-primary btn-lg fw-bold px-5" id="renewalSubmitBtn" style="border-radius:10px; box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);">
                <span id="renewalSubmitText"><i class="fas fa-paper-plane me-2"></i>Submit Application</span>
                <span id="renewalSubmitSpinner" class="d-none">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span> Submitting...
                </span>
            </button>
            <p class="text-muted mt-2 mb-0" style="font-size:.85rem;">By submitting, your old files will be replaced with the newly uploaded ones.</p>
        </div>
    </form>
    @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap Tooltips
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    }

    const container = document.getElementById('instructorCardsContainer');
    if (container) {
    
    const template = document.getElementById('instructorTemplate');
    const addBtn = document.getElementById('addInstructorBtn');
    
    let cardCount = container.querySelectorAll('.instructor-card').length;
    
    function relabelCards() {
        const cards = container.querySelectorAll('.instructor-card');
        cards.forEach((card, i) => {
            const span = card.querySelector('.instructor-label');
            if (span) {
                span.textContent = 'Instructor #' + (i + 1);
            }
            
            let removeBtn = card.querySelector('.remove-instructor-btn');
            if (cards.length > 1) {
                if (!removeBtn) {
                    removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-sm btn-outline-danger remove-instructor-btn';
                    removeBtn.innerHTML = '<i class="fas fa-trash me-1"></i>Remove';
                    removeBtn.addEventListener('click', function() {
                        card.remove();
                        relabelCards();
                    });
                    const header = card.querySelector('.d-flex.align-items-center.justify-content-between');
                    if(header) header.appendChild(removeBtn);
                } else {
                    removeBtn.classList.remove('d-none');
                }
            } else {
                if (removeBtn) removeBtn.classList.add('d-none');
            }
        });
    }

    container.querySelectorAll('.remove-instructor-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.instructor-card').remove();
            relabelCards();
        });
    });

    relabelCards();

    function reindexElement(el, idx) {
        ['name', 'id', 'for'].forEach(attr => {
            if (el.hasAttribute(attr)) {
                el.setAttribute(attr, el.getAttribute(attr).replace(/__IDX__/g, idx));
            }
        });
        el.querySelectorAll('[name],[id],[for]').forEach(child => {
            ['name', 'id', 'for'].forEach(attr => {
                if (child.hasAttribute(attr)) {
                    child.setAttribute(attr, child.getAttribute(attr).replace(/__IDX__/g, idx));
                }
            });
        });
    }

    if(addBtn) {
        addBtn.addEventListener('click', function() {
            const idx = cardCount++;
            const sourceNode = template.content ? template.content : template;
            const clone = sourceNode.querySelector('.instructor-card').cloneNode(true);

            reindexElement(clone, idx);

            clone.querySelector('.remove-instructor-btn').addEventListener('click', function() {
                clone.remove();
                relabelCards();
            });

            clone.style.opacity = '0';
            clone.style.transform = 'translateY(-8px)';
            clone.style.transition = 'opacity .25s ease, transform .25s ease';
            container.appendChild(clone);
            
            requestAnimationFrame(() => {
                clone.style.opacity = '1';
                clone.style.transform = 'translateY(0)';
            });

            relabelCards();
        });
    } // end if(addBtn)

    } // end if(container)

    /* ── Live Validation for Telephone, Fax, and Rep Contact ── */
    const telInput = document.getElementById('telephone');
    const faxInput = document.getElementById('fax');
    const repContactInput = document.getElementById('rep_contact');

    function validateLandline(input, typeName) {
        let val = input.value.replace(/[^0-9]/g, '');
        input.value = val;
        
        if (val.length === 10) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            input.setCustomValidity('');
        } else if (val.length === 0) {
            input.classList.remove('is-invalid', 'is-valid');
            input.setCustomValidity('');
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
            input.setCustomValidity(`Enter a valid 10-digit ${typeName} number (e.g. 0281234567).`);
        }
    }

    function validateRepContact(input) {
        let val = input.value.replace(/[^\d+]/g, '');
        if (val.startsWith('+')) {
            val = '+' + val.slice(1).replace(/\+/g, '');
        } else {
            val = val.replace(/\+/g, '');
        }
        input.value = val;

        const pattern = /^(09|\+639)\d{9}$/;
        if (val.length === 0) {
            if (input.hasAttribute('required')) {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                input.setCustomValidity('Contact number is required.');
            } else {
                input.classList.remove('is-invalid', 'is-valid');
                input.setCustomValidity('');
            }
        } else {
            if (pattern.test(val)) {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                input.setCustomValidity('');
            } else {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                input.setCustomValidity('Enter a valid PH mobile number (e.g. 09171234567).');
            }
        }
    }

    if (telInput) {
        // Run once on load to show current state if pre-filled
        if (telInput.value) validateLandline(telInput, 'telephone');
        telInput.addEventListener('input', function() {
            validateLandline(this, 'telephone');
        });
    }

    if (faxInput) {
        if (faxInput.value) validateLandline(faxInput, 'facsimile');
        faxInput.addEventListener('input', function() {
            validateLandline(this, 'facsimile');
        });
    }

    if (repContactInput) {
        if (repContactInput.value) validateRepContact(repContactInput);
        repContactInput.addEventListener('input', function() {
            validateRepContact(this);
        });
    }

    // Form submission loading state
    const renewalForm = document.getElementById('renewalForm');
    if (renewalForm) {
        renewalForm.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('was-validated');
                
                // Focus on the first invalid field for better UX
                const firstInvalid = this.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                this.classList.add('was-validated');
                const btn = document.getElementById('renewalSubmitBtn');
                const text = document.getElementById('renewalSubmitText');
                const spinner = document.getElementById('renewalSubmitSpinner');
                if (btn) btn.disabled = true;
                if (text) text.classList.add('d-none');
                if (spinner) spinner.classList.remove('d-none');
            }
        });
    }

    // Batch Resubmit Form Handling
    document.querySelectorAll('.batch-file-input').forEach(function (input) {
        input.addEventListener('change', function () {
            const wrapper = this.closest('div.file-upload-wrapper');
            const hint = wrapper.querySelector('.file-name-text');
            const btn = wrapper.querySelector('.custom-file-btn');
            const sectionLabel = wrapper.parentElement.querySelector('label.form-label');
            
            if (this.files.length > 0) {
                if (hint) {
                    hint.innerHTML = '<span class="text-dark fw-normal">File:</span> ' + this.files[0].name;
                    hint.classList.remove('text-muted');
                    hint.classList.add('text-success', 'fw-bold');
                }
                if (btn) {
                    btn.classList.remove('btn-outline-danger');
                    btn.classList.add('btn-success');
                    btn.style.cssText = 'border-color: #198754 !important; background-color: #198754 !important; color: white !important;';
                    btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> File Selected';
                }
                if (sectionLabel) {
                    sectionLabel.innerHTML = 'Ready for Resubmission <i class="bi bi-check2-circle"></i>';
                    sectionLabel.style.cssText = 'color: #198754 !important;';
                }
            } else {
                if (hint) {
                    hint.textContent = 'No file chosen';
                    hint.classList.add('text-muted');
                    hint.classList.remove('text-success', 'fw-bold');
                }
                if (btn) {
                    btn.classList.add('btn-outline-danger');
                    btn.classList.remove('btn-success');
                    btn.style.cssText = 'border-color:#842029; color:#842029; background-color: transparent;';
                    btn.innerHTML = '<i class="bi bi-cloud-upload me-1"></i> Choose File';
                }
                if (sectionLabel) {
                    sectionLabel.innerHTML = 'Upload Replacement (PDF) <span class="text-danger">*</span>';
                    sectionLabel.style.cssText = 'color: #842029 !important;';
                }
            }
        });
    });

    // ─── Batch Resubmit: Global handler called directly from button onclick ───
    window.handleResubmitSubmit = function(e) {
        const batchForm   = document.getElementById('batch-resubmit-form');
        const batchBtn    = document.getElementById('btn-resubmit-all');
        const batchBanner = document.getElementById('batch-form-error-banner');

        if (!batchForm) return;

        const fileInputs = Array.from(batchForm.querySelectorAll('.batch-file-input'));
        let firstErrorWrapper = null;
        let hasError = false;

        fileInputs.forEach(function(input) {
            const wrapper = input.closest('div.file-upload-wrapper');
            if (input.files.length === 0) {
                hasError = true;
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                if (wrapper) {
                    const feedback = wrapper.querySelector('.file-invalid-feedback');
                    if (feedback) feedback.style.display = 'block';
                    const btn = wrapper.querySelector('.custom-file-btn');
                    if (btn) {
                        btn.classList.remove('btn-success');
                        btn.style.cssText = 'border-color:#dc3545 !important; color:#dc3545 !important; background-color:#fff5f5 !important;';
                        btn.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Required';
                    }
                    if (!firstErrorWrapper) firstErrorWrapper = wrapper;
                }
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                if (wrapper) {
                    const feedback = wrapper.querySelector('.file-invalid-feedback');
                    if (feedback) feedback.style.display = 'none';
                }
            }
        });

        if (hasError) {
            if (batchBanner) batchBanner.style.display = 'block';
            const scrollTarget = firstErrorWrapper || batchBanner;
            if (scrollTarget) scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return; // stop — do NOT submit
        }

        // All files present — hide banner and submit
        if (batchBanner) batchBanner.style.display = 'none';
        if (batchBtn) {
            batchBtn.disabled = true;
            batchBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting…';
        }
        batchForm.submit();
    };

    // Payment Upload File Input Handling
    document.querySelectorAll('.payment-file-input').forEach(function (input) {
        input.addEventListener('change', function () {
            const wrapper = this.closest('div.file-upload-wrapper');
            const hint = wrapper.querySelector('.file-name-text');
            const btn = wrapper.querySelector('.custom-file-btn');
            
            if (this.files.length > 0) {
                if (hint) {
                    hint.innerHTML = '<span class="text-dark fw-normal">File:</span> ' + this.files[0].name;
                    hint.classList.remove('text-muted');
                    hint.classList.add('text-success', 'fw-bold');
                }
                if (btn) {
                    btn.classList.remove('btn-outline-info');
                    btn.classList.add('btn-success');
                    btn.style.cssText = 'border-color: #198754 !important; background-color: #198754 !important; color: white !important;';
                    btn.innerHTML = '<i class="fas fa-check me-1"></i> File Selected';
                }
            } else {
                if (hint) {
                    hint.textContent = 'No file chosen';
                    hint.classList.add('text-muted');
                    hint.classList.remove('text-success', 'fw-bold');
                }
                if (btn) {
                    btn.classList.add('btn-outline-info');
                    btn.classList.remove('btn-success');
                    btn.style.cssText = 'border-color: #17a2b8; color: #17a2b8; background-color: transparent;';
                    btn.innerHTML = '<i class="fas fa-cloud-upload-alt me-1"></i> Choose File';
                }
            }
        });
    });

    const paymentForm = document.getElementById('payment-upload-form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function (e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('was-validated');
            } else {
                this.classList.add('was-validated');
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Uploading…';
                }
            }
        });
    }
});
</script>

<style>
    .file-upload-wrapper .custom-file-btn {
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .was-validated .real-file-input:invalid ~ .file-invalid-feedback,
    .real-file-input.is-invalid ~ .file-invalid-feedback {
        display: block !important;
    }
    .was-validated .real-file-input:invalid ~ div .custom-file-btn,
    .real-file-input.is-invalid ~ div .custom-file-btn {
        border-color: var(--bs-danger) !important;
        color: var(--bs-danger) !important;
        background-color: #fff5f5 !important;
    }
    .real-file-input:valid ~ div .custom-file-btn {
        background-color: #198754 !important;
        border-color: #198754 !important;
        color: white !important;
    }

    /* Highlight empty required file inputs in red when invalid/submitted */
    .was-validated input[type="file"]:invalid,
    input[type="file"].is-invalid {
        border-color: #dc3545 !important;
        background-color: #fff8f8 !important;
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
    }

    /* Highlight standard inputs when in valid state */
    .was-validated input[type="file"]:valid,
    input[type="file"].is-valid {
        border-color: #198754 !important;
        background-color: #f8fff9 !important;
    }

    /* Fix for Bootstrap 5 file input validation huge space bug */
    input[type="file"]:valid,
    input[type="file"].is-valid,
    input[type="file"]:invalid,
    input[type="file"].is-invalid {
        background-image: none !important;
        padding-right: 0 !important;
    }

    /* Fix for file name truncation */
    .file-name-text.text-truncate {
        display: inline-block;
        vertical-align: middle;
    }
</style>
@endpush
