@extends('layouts.landing')

@section('title', 'Track Application | ARMS')

@section('content')
<section class="section-py bg-light" style="min-height: 70vh; padding-top: 120px;">
    <div class="container pb-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                {{-- Status Messages --}}
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                {{-- Track Search Box --}}
                <div class="card shadow-sm border-0 mt-5 mb-4">
                    <div class="card-body p-5 text-center">
                        <i class="bi bi-search" style="font-size: 3rem; color: #f5b041;"></i>
                        <h2 class="mt-3 mb-4">Track Your Application</h2>
                        <p class="text-muted mb-4">Enter your application tracking number below to check the current status of your accreditation.</p>

                        <form action="{{ route('track') }}" method="GET">
                            <div class="input-group input-group-lg mb-3">
                                <span class="input-group-text bg-white border-end" style="border-right: 1px solid #000000ff; !important"><i class="bi bi-hash fw-bold" style="color: #0b3d91;"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" name="tracking_number" placeholder="Enter Tracking Number (e.g. ARMS-2026-000001)" value="{{ request('tracking_number') }}" required>
                                <button class="btn btn-primary px-4 fw-semibold" type="submit" style="background-color: #0b3d91; border-color: #0b3d91;">Track Status</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Track Results --}}
                @if(request()->has('tracking_number'))
                    @if($application)
                        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; overflow: hidden;">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                                <h4 class="mb-0" style="color: #0b3d91; font-weight: 700;">Application Details</h4>
                            </div>
                            <div class="card-body p-4">
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <p class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">Tracking Number</p>
                                        <p class="h5 text-dark fw-bold">{{ $application->tracking_number }}</p>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <p class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">Current Status</p>
                                        <span class="badge rounded-pill bg-info text-dark px-3 py-2 fs-6" style="background-color: #e0f2fe !important; color: #0369a1 !important;">
                                            {{ $application->latestStatus?->status?->name ?? 'Submitted' }}
                                        </span>
                                    </div>
                                </div>

                                {{-- ── Interview Schedule Banner ── --}}
                                @php $interview = $application->interview; @endphp
                                @if($interview && $application->latestStatus?->status?->name === 'Scheduled for Interview')
                                <div class="rounded-3 border mb-4 overflow-hidden" style="border-color:#bfdbfe !important;">
                                    <div class="px-4 py-2 fw-bold d-flex align-items-center gap-2"
                                         style="background:#1d4ed8; color:#fff; font-size:.9rem;">
                                        <i class="bi bi-camera-video-fill"></i> Your Interview Has Been Scheduled
                                    </div>
                                    <div class="px-4 py-3" style="background:#eff6ff;">
                                        <div class="row g-3">
                                            <div class="col-6 col-md-3">
                                                <p class="text-uppercase text-muted fw-bold mb-1" style="font-size:.72rem; letter-spacing:.4px;">Date</p>
                                                <p class="mb-0 fw-bold" style="color:#1e3a5f; font-size:.95rem;">
                                                    <i class="bi bi-calendar-event me-1 text-primary"></i>
                                                    {{ $interview->interview_date->format('F d, Y') }}
                                                </p>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <p class="text-uppercase text-muted fw-bold mb-1" style="font-size:.72rem; letter-spacing:.4px;">Time</p>
                                                <p class="mb-0 fw-bold" style="color:#1e3a5f; font-size:.95rem;">
                                                    <i class="bi bi-clock me-1 text-primary"></i>
                                                    {{ \Carbon\Carbon::parse($interview->interview_time)->format('h:i A') }}
                                                </p>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <p class="text-uppercase text-muted fw-bold mb-1" style="font-size:.72rem; letter-spacing:.4px;">Mode</p>
                                                <p class="mb-0 fw-bold" style="color:#1e3a5f; font-size:.95rem;">
                                                    @if($interview->mode === 'online')
                                                        <i class="bi bi-camera-video me-1 text-primary"></i> Online
                                                    @else
                                                        <i class="bi bi-people me-1 text-primary"></i> Face-to-Face
                                                    @endif
                                                </p>
                                            </div>
                                            @if($interview->venue)
                                            <div class="col-6 col-md-3">
                                                <p class="text-uppercase text-muted fw-bold mb-1" style="font-size:.72rem; letter-spacing:.4px;">Venue</p>
                                                <p class="mb-0 fw-bold" style="color:#1e3a5f; font-size:.95rem;">
                                                    <i class="bi bi-geo-alt me-1 text-primary"></i>
                                                    {{ $interview->venue }}
                                                </p>
                                            </div>
                                            @endif
                                        </div>
                                        <p class="mb-0 mt-3 small text-muted">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Please be ready on the scheduled date and time. Contact our office if you need to reschedule.
                                        </p>
                                    </div>
                                </div>
                                @endif
                                <hr class="my-4" style="border-color: #e9ecef;">
                                <h5 class="mb-3 fw-bold" style="color: #1a2e5a;">Submitted Documents</h5>

                                @php
                                    // Group documents by their document type (section)
                                    $grouped = $application->documents->groupBy(fn($doc) => optional($doc->documentField?->documentType)->id);
                                @endphp

                                <div class="d-flex flex-column gap-4">
                                @foreach($grouped as $typeId => $docs)
                                    @php
                                        $sectionName = optional($docs->first()?->documentField?->documentType)->name ?? 'Other Documents';
                                    @endphp

                                    {{-- Section / Type Label --}}
                                    <div class="border rounded-3 overflow-hidden">
                                        <div class="px-3 py-2 fw-bold" style="background:#f0f4ff; color:#0b3d91; font-size:.85rem; letter-spacing:.3px;">
                                            <i class="bi bi-folder2-open me-2"></i>{{ $sectionName }}
                                        </div>

                                        <div class="list-group list-group-flush">
                                        @foreach($docs as $doc)
                                            <div class="list-group-item px-3 py-3 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2">
                                                <div class="flex-grow-1">
                                                    {{-- Field name --}}
                                                    <h6 class="mb-1 fw-semibold" style="font-size:.9rem;">
                                                        {{ $doc->documentField?->name ?? 'Document' }}
                                                    </h6>

                                                    {{-- Status badge + file icon --}}
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        @if($doc->status === 'approved')
                                                            <span class="badge" style="background:#dcfce7; color:#166534;"><i class="bi bi-check-circle me-1"></i>Approved</span>
                                                        @elseif(in_array($doc->status, ['returned','rejected']))
                                                            <span class="badge" style="background:#fee2e2; color:#991b1b;"><i class="bi bi-x-circle me-1"></i>Requires Resubmission</span>
                                                        @else
                                                            <span class="badge" style="background:#fef9c3; color:#854d0e;"><i class="bi bi-clock me-1"></i>Pending Review</span>
                                                        @endif

                                                        @if($doc->documentField?->input_type === 'file')
                                                            <span class="text-muted small"><i class="bi bi-file-earmark-pdf"></i> Uploaded file</span>
                                                        @elseif($doc->documentField?->input_type === 'date')
                                                            <span class="text-muted small"><i class="bi bi-calendar"></i> {{ $doc->userDocument?->value ?? '—' }}</span>
                                                        @else
                                                            <span class="text-muted small"><i class="bi bi-chat-square-text"></i> {{ $doc->userDocument?->value ?? '—' }}</span>
                                                        @endif
                                                    </div>

                                                    {{-- Evaluator remarks --}}
                                                    @if(in_array($doc->status, ['returned','rejected']) && $doc->remarks)
                                                        <div class="alert py-2 px-3 border-0 rounded mt-2 mb-0" style="background:#fee2e2; color:#7f1d1d; font-size:.85rem;">
                                                            <strong><i class="bi bi-chat-left-text-fill me-1"></i>Evaluator Remarks:</strong>
                                                            <p class="mb-0 mt-1">{{ $doc->remarks }}</p>
                                                        </div>
                                                    @endif
                                                </div>

                                                 {{-- Resubmission file picker lives in the batch form below --}}
                                                @if(in_array($doc->status, ['returned','rejected']) && $doc->documentField?->input_type === 'file')
                                                    <div class="mt-1">
                                                        <span class="badge" style="background:#fff3cd; color:#856404; font-size:.75rem;">
                                                            <i class="bi bi-arrow-up-circle me-1"></i>Attach replacement below
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                        </div>
                                    </div>
                                @endforeach
                                </div>

                                {{-- Instructor Credentials Tracking --}}
                                @if($application->user && $application->user->instructors->count() > 0)
                                    <div class="border rounded-3 overflow-hidden mt-4">
                                        <div class="px-3 py-2 fw-bold" style="background:#f0f4ff; color:#0b3d91; font-size:.85rem; letter-spacing:.3px;">
                                            <i class="bi bi-person-badge me-2"></i>Instructor Credentials
                                        </div>

                                        <div class="list-group list-group-flush">
                                        @foreach($application->user->instructors as $instructor)
                                            {{-- Instructor Service Agreement --}}
                                            <div class="list-group-item px-3 py-3 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2" style="background-color: #fafbfe;">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 fw-semibold" style="font-size:.9rem; color:#1e3a5f;">
                                                        {{ $instructor->first_name }} {{ $instructor->last_name }} &mdash; Service Agreement
                                                    </h6>
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        @if($instructor->status === 'approved')
                                                            <span class="badge" style="background:#dcfce7; color:#166534;"><i class="bi bi-check-circle me-1"></i>Approved</span>
                                                        @elseif(in_array($instructor->status, ['returned','rejected']))
                                                            <span class="badge" style="background:#fee2e2; color:#991b1b;"><i class="bi bi-x-circle me-1"></i>Requires Resubmission</span>
                                                        @else
                                                            <span class="badge" style="background:#fef9c3; color:#854d0e;"><i class="bi bi-clock me-1"></i>Pending Review</span>
                                                        @endif
                                                        <span class="text-muted small"><i class="bi bi-file-earmark-pdf"></i> Uploaded PDF</span>
                                                    </div>
                                                    @if(in_array($instructor->status, ['returned','rejected']) && $instructor->remarks)
                                                        <div class="alert py-2 px-3 border-0 rounded mt-2 mb-0" style="background:#fee2e2; color:#7f1d1d; font-size:.85rem;">
                                                            <strong><i class="bi bi-chat-left-text-fill me-1"></i>Evaluator Remarks:</strong>
                                                            <p class="mb-0 mt-1">{{ $instructor->remarks }}</p>
                                                        </div>
                                                    @endif
                                                </div>
                                                @if(in_array($instructor->status, ['returned','rejected']))
                                                    <div class="mt-1">
                                                        <span class="badge" style="background:#fff3cd; color:#856404; font-size:.75rem;">
                                                            <i class="bi bi-arrow-down-circle me-1"></i>Attach replacement below
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Instructor Credentials --}}
                                            @foreach($instructor->credentials as $cred)
                                                <div class="list-group-item px-3 py-3 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2 ps-4">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fw-semibold" style="font-size:.9rem; color:#495057;">
                                                            <span class="badge bg-secondary me-2" style="font-size:.7rem;">{{ $cred->type }}</span>{{ $cred->certificate_number }}
                                                        </h6>
                                                        <div class="d-flex align-items-center gap-2 mb-1">
                                                            @if($cred->status === 'approved')
                                                                <span class="badge" style="background:#dcfce7; color:#166534;"><i class="bi bi-check-circle me-1"></i>Approved</span>
                                                            @elseif(in_array($cred->status, ['returned','rejected']))
                                                                <span class="badge" style="background:#fee2e2; color:#991b1b;"><i class="bi bi-x-circle me-1"></i>Requires Resubmission</span>
                                                            @else
                                                                <span class="badge" style="background:#fef9c3; color:#854d0e;"><i class="bi bi-clock me-1"></i>Pending Review</span>
                                                            @endif
                                                            <span class="text-muted small"><i class="bi bi-file-earmark-pdf"></i> Uploaded PDF</span>
                                                        </div>
                                                        @if(in_array($cred->status, ['returned','rejected']) && $cred->remarks)
                                                            <div class="alert py-2 px-3 border-0 rounded mt-2 mb-0" style="background:#fee2e2; color:#7f1d1d; font-size:.85rem;">
                                                                <strong><i class="bi bi-chat-left-text-fill me-1"></i>Evaluator Remarks:</strong>
                                                                <p class="mb-0 mt-1">{{ $cred->remarks }}</p>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    @if(in_array($cred->status, ['returned','rejected']))
                                                        <div class="mt-1">
                                                            <span class="badge" style="background:#fff3cd; color:#856404; font-size:.75rem;">
                                                                <i class="bi bi-arrow-down-circle me-1"></i>Attach replacement below
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endforeach
                                        </div>
                                    </div>
                                @endif

                            {{-- ── Batch Resubmission Form (shown only when there are rejected file docs) ── --}}
                            @php
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
                            <div class="mt-4 p-4 border rounded-3" style="background:#fff8f8; border-color:#f5c6cb !important;">
                                <h6 class="fw-bold mb-3" style="color:#842029;">
                                    <i class="bi bi-arrow-repeat me-2"></i>Resubmit Rejected Documents
                                </h6>

                                <form action="{{ route('track.resubmit.all') }}" method="POST" enctype="multipart/form-data" id="batch-resubmit-form">
                                    @csrf
                                    <input type="hidden" name="application_id" value="{{ $application->id }}">

                                    <div class="d-flex flex-column gap-3">
                                        {{-- Standard Documents --}}
                                        @foreach($rejectedDocs as $rdoc)
                                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3 p-3 bg-white rounded-2 border">
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

                                        {{-- Instructor Service Agreements --}}
                                        @foreach($rejectedInstructors as $rInst)
                                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3 p-3 bg-white rounded-2 border">
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
                                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3 p-3 bg-white rounded-2 border">
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

                                    <div class="mt-4 text-end">
                                        <button type="submit" class="btn btn-danger fw-bold px-5" id="btn-resubmit-all">
                                            <i class="bi bi-send-fill me-2"></i>
                                            Resubmit All Documents ({{ $totalRejected }})
                                        </button>
                                    </div>
                                </form>
                            </div>
                            @endif

                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning text-center border-0 shadow-sm p-4 rounded-3" style="background-color: #fff8e6;">
                            <div class="mb-3">
                                <i class="bi bi-exclamation-circle text-warning" style="font-size: 3rem;"></i>
                            </div>
                            <h4 class="alert-heading fw-bold" style="color: #856404;">No Application Found</h4>
                            <p class="mb-0 text-dark">We couldn't find any application matching the tracking number <strong>{{ request('tracking_number') }}</strong>.</p>
                            <p class="small text-muted mt-2">Please double-check the tracking number from your email and try again.</p>
                        </div>
                    @endif
                @endif

                <div class="text-center mt-4 mb-5">
                    <a href="{{ url('/') }}" class="text-decoration-none fw-semibold" style="color: #0b3d91;">
                        <i class="bi bi-arrow-left me-1"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Show filename next to each file input after selection
    document.querySelectorAll('.batch-file-input').forEach(function (input) {
        input.addEventListener('change', function () {
            const hint = this.closest('div.file-upload-wrapper').querySelector('.file-name-text');
            if (hint && this.files.length > 0) {
                hint.textContent = this.files[0].name;
            } else if (hint) {
                hint.textContent = 'No file chosen';
            }
        });
    });

    // Prevent double-submit
    const form = document.getElementById('batch-resubmit-form');
    const btn  = document.getElementById('btn-resubmit-all');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting…';
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
        background-color: transparent !important;
    }
    .was-validated .real-file-input:valid ~ div .custom-file-btn {
        background-color: var(--bs-success) !important;
        border-color: var(--bs-success) !important;
        color: white !important;
    }
</style>
@endsection