@extends('layouts.applicant')

@section('title', 'Notice to Conduct')

@section('content')
<div class="row">
    <div class="page-title">
        <div class="title_left">
            <h3><i class="fas fa-clipboard-list" style="color: var(--portal-gold);"></i> Notice to Conduct (NTC)</h3>
        </div>
    </div>
</div>

<div class="clearfix"></div>

{{-- Flash Messages --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->has('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> {{ $errors->first('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- No Active Accreditation Warning --}}
@if(!$accreditation)
    <div class="row">
        <div class="col-md-12">
            <div class="x_panel" style="border-left: 4px solid #e74c3c;">
                <div class="x_content">
                    <div class="text-center py-4">
                        <i class="fas fa-lock" style="font-size: 3rem; color: #e74c3c; margin-bottom: 1rem; display:block;"></i>
                        <h4 class="fw-bold text-danger">No Active Accreditation</h4>
                        <p class="text-muted">You must have an <strong>active accreditation</strong> before you can submit a Notice to Conduct (NTC) report.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@else

{{-- ── ROW 0a: ACCREDITATION SUMMARY CARD (dashboard-style) ── --}}
<div class="row">
    <div class="col-md-12 col-sm-12">
        <div class="x_panel" style="border-left: 4px solid var(--portal-gold); border-top: none;">
            <div class="x_title border-0 mb-0 pb-0">
                <h2 class="fw-bold" style="color: #2A3F54;"><i class="fas fa-award text-warning me-2"></i> Accreditation Summary</h2>
                <div class="clearfix"></div>
            </div>
            <div class="x_content mt-2">
                <div class="row text-center text-md-start">
                    <div class="col-md mb-2 mb-md-0 border-end">
                        <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Accreditation Number</p>
                        <p class="fw-bold fs-5 mb-0" style="color: #0b3d91;">{{ $accreditation->accreditation_number ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md mb-2 mb-md-0 border-end">
                        <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Date Accredited</p>
                        <p class="fw-bold fs-5 mb-0" style="color: #2A3F54;">
                            {{ $accreditation->date_of_accreditation ? \Carbon\Carbon::parse($accreditation->date_of_accreditation)->format('F d, Y') : 'N/A' }}
                        </p>
                    </div>
                    <div class="col-md mb-2 mb-md-0 border-end">
                        <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Validity Period</p>
                        <p class="fw-bold fs-5 mb-0" style="color: #2A3F54;">
                            {{ $accreditation->validity_date ? \Carbon\Carbon::parse($accreditation->validity_date)->format('F d, Y') : 'N/A' }}
                        </p>
                    </div>
                    <div class="col-md">
                        <p class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase;">Status</p>
                        <p class="mb-0 mt-1">
                            <span class="badge bg-success" style="font-size: 0.9rem; padding: 6px 12px;">Active</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── ROW 0b: REGULATORY REMINDER ────────────────────────────── --}}
<div class="row" style="margin-bottom: 20px;">
    <div class="col-md-12">
        <div style="
            background: #fff8e6;
            border: 1px solid #f5d98a;
            border-left: 5px solid #D4AC4B;
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 0.87rem;
            color: #7a5c00;
            display: block;
            visibility: visible;
            opacity: 1;
        ">
            <div style="margin-bottom: 6px;">
                <i class="fas fa-exclamation-triangle" style="margin-right: 5px;"></i>
                <strong>Regulatory Reminder (OSHC MC 04 Series 2025):</strong>
            </div>
            <p style="margin-bottom: 6px;">
                Notice to Conduct must be submitted at least <strong>ten (10) working days</strong> before the first training day
                using the <strong>DOLE-OSHC-STO-RTCMan</strong> and <strong>DOLE-OSHC-STO-PROG</strong> forms as per <strong>OSHC MC 04 Series 2025</strong>.
            </p>
            <p style="margin-bottom: 0;">
                <i class="fas fa-calendar-check" style="margin-right: 5px;"></i>
                Earliest allowed training start date:
                <strong>{{ \Carbon\Carbon::parse($earliestStartDate)->format('F d, Y') }}</strong>.
            </p>
        </div>
    </div>
</div>

{{-- ── ROW 1: SUBMIT NEW NTC FORM ──────────────────────────── --}}
<div class="row">
    <div class="col-md-12">
        <div class="x_panel" style="border-top: 3px solid var(--portal-gold);">
            <div class="x_title">
                <h2><i class="fas fa-paper-plane me-2" style="color: var(--portal-gold);"></i>Submit New NTC</h2>
                <div class="clearfix"></div>
            </div>
            <div class="x_content">

                <form method="POST"
                      action="{{ route('applicant.ntc.store') }}"
                      enctype="multipart/form-data"
                      id="ntcSubmitForm"
                      novalidate>
                    @csrf

                    {{-- Training Type --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="ntc_training_type_id">
                            Type of Training <span class="text-danger">*</span>
                        </label>
                        <select id="ntc_training_type_id"
                                name="ntc_training_type_id"
                                class="form-control @error('ntc_training_type_id') is-invalid @enderror"
                                required>
                            <option value="" disabled selected>— Select Training Type —</option>
                            @foreach($trainingTypes as $type)
                                <option value="{{ $type->id }}"
                                    {{ old('ntc_training_type_id') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('ntc_training_type_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Mode of Training --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="ntc_training_mode_id">
                            Mode of Training <span class="text-danger">*</span>
                        </label>
                        <select id="ntc_training_mode_id"
                                name="ntc_training_mode_id"
                                class="form-control @error('ntc_training_mode_id') is-invalid @enderror"
                                required>
                            <option value="" disabled selected>— Select Mode —</option>
                            @foreach($trainingModes as $mode)
                                <option value="{{ $mode->id }}"
                                    {{ old('ntc_training_mode_id') == $mode->id ? 'selected' : '' }}>
                                    {{ $mode->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('ntc_training_mode_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Training Dates --}}
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label class="fw-semibold" for="training_start_date">
                                    Training Start Date <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                       id="training_start_date"
                                       name="training_start_date"
                                       class="form-control @error('training_start_date') is-invalid @enderror"
                                       min="{{ $earliestStartDate }}"
                                       value="{{ old('training_start_date') }}"
                                       required>
                                @error('training_start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label class="fw-semibold" for="training_end_date">
                                    Training End Date <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                       id="training_end_date"
                                       name="training_end_date"
                                       class="form-control @error('training_end_date') is-invalid @enderror"
                                       value="{{ old('training_end_date') }}"
                                       required>
                                @error('training_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- File: RTCMan Form --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="file_rtcman">
                            DOLE-OSHC-STO-RTCMan Form <span class="text-danger">*</span>
                        </label>
                        <p class="text-muted mb-1" style="font-size:0.8rem;">
                            Accepted formats: <code>.pdf</code>, <code>.doc</code>, <code>.docx</code> &mdash; Max 100 MB
                        </p>
                        <div class="ntc-file-drop-zone @error('file_rtcman') is-invalid-zone @enderror"
                             id="dropZoneRtcman"
                             data-input="file_rtcman">
                            <div class="ntc-drop-zone-content">
                                <div class="state-empty">
                                    <i class="fas fa-cloud-upload-alt ntc-file-icon"></i>
                                    <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                                    <p class="ntc-file-selected text-muted">No file selected</p>
                                </div>
                                <div class="state-selected d-none">
                                    <i class="fas fa-check-circle text-success fs-4 mb-2"></i>
                                    <p class="selected-file-title fw-bold text-success mb-1">File ready to upload</p>
                                    <p class="selected-file-info mb-2 text-dark font-monospace" style="font-size: 0.78rem;"></p>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-clear-file no-trigger py-1 px-3" style="font-size: 0.72rem; border-radius: 20px;">
                                        <i class="fas fa-trash-alt me-1"></i> Clear Selection
                                    </button>
                                </div>
                            </div>
                            <input type="file"
                                   id="file_rtcman"
                                   name="file_rtcman"
                                   class="d-none ntc-file-input"
                                   accept=".pdf,.doc,.docx">
                        </div>
                        <div class="invalid-feedback-custom text-danger mt-1 d-none" id="error_file_rtcman" style="font-size: 0.85rem;">
                            Please upload the DOLE-OSHC-STO-RTCMan Form.
                        </div>
                        @error('file_rtcman')
                            <div class="text-danger mt-1" style="font-size: 0.85rem;">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- File: PROG Form --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="file_prog">
                            DOLE-OSHC-STO-PROG Form <span class="text-danger">*</span>
                        </label>
                        <p class="text-muted mb-1" style="font-size:0.8rem;">
                            Accepted formats: <code>.pdf</code>, <code>.doc</code>, <code>.docx</code> &mdash; Max 100 MB
                        </p>
                        <div class="ntc-file-drop-zone @error('file_prog') is-invalid-zone @enderror"
                             id="dropZoneProg"
                             data-input="file_prog">
                            <div class="ntc-drop-zone-content">
                                <div class="state-empty">
                                    <i class="fas fa-cloud-upload-alt ntc-file-icon"></i>
                                    <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                                    <p class="ntc-file-selected text-muted">No file selected</p>
                                </div>
                                <div class="state-selected d-none">
                                    <i class="fas fa-check-circle text-success fs-4 mb-2"></i>
                                    <p class="selected-file-title fw-bold text-success mb-1">File ready to upload</p>
                                    <p class="selected-file-info mb-2 text-dark font-monospace" style="font-size: 0.78rem;"></p>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-clear-file no-trigger py-1 px-3" style="font-size: 0.72rem; border-radius: 20px;">
                                        <i class="fas fa-trash-alt me-1"></i> Clear Selection
                                    </button>
                                </div>
                            </div>
                            <input type="file"
                                   id="file_prog"
                                   name="file_prog"
                                   class="d-none ntc-file-input"
                                   accept=".pdf,.doc,.docx">
                        </div>
                        <div class="invalid-feedback-custom text-danger mt-1 d-none" id="error_file_prog" style="font-size: 0.85rem;">
                            Please upload the DOLE-OSHC-STO-PROG Form.
                        </div>
                        @error('file_prog')
                            <div class="text-danger mt-1" style="font-size: 0.85rem;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <button type="submit"
                                id="ntcSubmitBtn"
                                class="btn btn-block fw-bold"
                                style="background: linear-gradient(135deg, #D4AC4B, #b8922e); color: #fff; border: none; padding: 12px; border-radius: 8px; font-size: 1rem; letter-spacing: 0.5px;">
                            <i class="fas fa-paper-plane me-2"></i> Submit Notice to Conduct
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

{{-- ── ROW 2: NTC SUBMISSION HISTORY ────────────────────────── --}}
<div class="row">
    <div class="col-md-12">
        <div class="card ntc-premium-card mb-4">
            <div class="card-header border-0 bg-transparent py-3 d-flex align-items-center justify-content-between">
                <h4 class="m-0 fw-bold" style="color: #2A3F54;">
                    <i class="fas fa-history me-2" style="color: var(--portal-gold);"></i> My NTC Submissions
                </h4>
            </div>
            <div class="card-body p-0">

                @if($ntcReports->isEmpty())
                    <div class="text-center py-5">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; display:block; margin-bottom: 1rem;"></i>
                        <p class="text-muted">No NTC submissions yet. Fill out the form to submit your first Notice to Conduct.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table ntc-table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th class="ps-4" style="color: #475569; width: 180px;">Reference #</th>
                                    <th style="color: #475569;">Type</th>
                                    <th style="color: #475569;">Mode</th>
                                    <th style="color: #475569;">Submitted</th>
                                    <th style="color: #475569;">Training Period</th>
                                    <th style="color: #475569;">Status</th>
                                    <th class="pe-4" style="color: #475569; width: 350px;">Documents</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ntcReports as $ntc)
                                @php
                                    // True rejected: status is rejected AND file has been deleted/removed
                                    $hasRejected = $ntc->documents->contains(fn($d) => $d->status === 'rejected' && !$d->file_path);
                                    $rtcmanDoc = $ntc->documents->first(fn($d) => $d->documentType->code === 'RTCMAN');
                                    $progDoc = $ntc->documents->first(fn($d) => $d->documentType->code === 'PROG');
                                @endphp
                                <tr style="{{ $hasRejected ? 'background: #fff8f8;' : '' }}">
                                    <td class="ps-4">
                                        <div class="ntc-ref-link">NTC-{{ str_pad($ntc->id, 6, '0', STR_PAD_LEFT) }}</div>
                                        @if($hasRejected)
                                            <div style="margin-top:4px;">
                                                <span class="badge bg-danger d-inline-flex align-items-center gap-1" style="font-size:.68rem; padding: 4px 8px; border-radius: 4px; font-weight: 700;">
                                                    <i class="fas fa-exclamation-triangle"></i> Action Required
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge"
                                              style="background: #eef5ff; color: #0b3d91; font-size: 0.75rem; padding: 5px 10px; border-radius: 20px; font-weight: 600; border: 1px solid #eef2f6;">
                                            {{ $ntc->trainingType->code ?? 'N/A' }}
                                        </span>
                                        <div class="text-secondary mt-1" style="font-size:0.75rem; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $ntc->trainingType->name ?? '' }}">
                                            {{ $ntc->trainingType->name ?? '' }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold" style="color: #334155;">{{ $ntc->trainingMode->name ?? 'N/A' }}</span>
                                    </td>
                                    <td>
                                        @if($ntc->submitted_at)
                                            <div style="font-size: 0.85rem; color: #475569; font-weight: 500; white-space: nowrap;">
                                                {{ $ntc->submitted_at->format('F d, Y') }}
                                            </div>
                                        @else
                                            <span class="text-muted" style="font-size: 0.85rem;">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div style="font-size: 0.85rem; color: #475569; white-space: nowrap;">
                                            <span class="fw-semibold text-secondary">Start:</span> {{ $ntc->training_start_date ? $ntc->training_start_date->format('F d, Y') : 'N/A' }}
                                        </div>
                                        <div class="mt-1" style="font-size: 0.85rem; color: #475569; white-space: nowrap;">
                                            <span class="fw-semibold text-secondary">End:</span> {{ $ntc->training_end_date ? $ntc->training_end_date->format('F d, Y') : 'N/A' }}
                                        </div>
                                    </td>
                                    <td>
                                        @if($ntc->status === 'acknowledged')
                                            <span class="badge badge-premium-success">Acknowledged</span>
                                            @if($ntc->canSubmitReportChanges())
                                                <div class="mt-2">
                                                    <button type="button"
                                                            class="btn btn-xs btn-outline-primary fw-bold px-2 py-1 mt-1 btn-report-changes"
                                                            data-id="{{ $ntc->id }}"
                                                            data-training-type="{{ $ntc->ntc_training_type_id }}"
                                                            data-training-mode="{{ $ntc->ntc_training_mode_id }}"
                                                            data-start-date="{{ $ntc->training_start_date ? $ntc->training_start_date->format('Y-m-d') : '' }}"
                                                            data-end-date="{{ $ntc->training_end_date ? $ntc->training_end_date->format('Y-m-d') : '' }}"
                                                            data-rtcman-file-name="{{ $rtcmanDoc ? $rtcmanDoc->original_filename : '' }}"
                                                            data-rtcman-file-url="{{ $rtcmanDoc && $rtcmanDoc->file_path ? route('applicant.ntc.document.view', $rtcmanDoc->id) : '' }}"
                                                            data-prog-file-name="{{ $progDoc ? $progDoc->original_filename : '' }}"
                                                            data-prog-file-url="{{ $progDoc && $progDoc->file_path ? route('applicant.ntc.document.view', $progDoc->id) : '' }}"
                                                            style="font-size: 0.72rem; border-radius: 6px;">
                                                        <i class="fas fa-exchange-alt me-1"></i> Report of Changes
                                                    </button>
                                                </div>
                                            @endif
                                            @if($ntc->reportChangesDeadlineDate())
                                                <div class="text-muted mt-1" style="font-size:0.7rem;">
                                                    Changes until: {{ $ntc->reportChangesDeadlineDate()->format('F d, Y') }}
                                                </div>
                                            @endif
                                        @elseif($ntc->status === 'report_changes')
                                            <span class="badge bg-info text-white" style="font-size: 0.75rem; padding: 5px 10px; border-radius: 20px; font-weight: 600;">Report of Changes</span>
                                        @elseif($hasRejected)
                                            <span class="badge badge-premium-danger">Requires Re-submission</span>
                                        @elseif($ntc->status === 'submitted')
                                            <span class="badge badge-premium-warning">Submitted</span>
                                        @else
                                            <span class="badge badge-premium-secondary">{{ ucfirst($ntc->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="pe-4">
                                        @php
                                            $rejectedDocsForBatch = $ntc->documents->filter(fn($d) => $d->status === 'rejected' && !$d->file_path);
                                        @endphp

                                        @if($rejectedDocsForBatch->isNotEmpty())
                                        <form method="POST"
                                              action="{{ route('applicant.ntc.reupload_batch', $ntc->id) }}"
                                              enctype="multipart/form-data" class="ntc-reupload-form" novalidate>
                                            @csrf
                                        @endif

                                        @foreach($ntc->documents as $doc)
                                        @php
                                            $isTrueRejected = ($doc->status === 'rejected' && !$doc->file_path);
                                            $isReturned = ($doc->status === 'returned');
                                            
                                            if ($isTrueRejected) {
                                                $docBadge = ['badge-premium-danger', 'Rejected'];
                                            } elseif ($isReturned) {
                                                $docBadge = ['badge-premium-warning', 'Awaiting Review'];
                                            } elseif ($doc->status === 'approved') {
                                                $docBadge = ['badge-premium-success', 'Approved'];
                                            } else {
                                                $docBadge = ['badge-premium-secondary', 'Under Review'];
                                            }
                                        @endphp
                                        <div class="ntc-doc-item">
                                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1 flex-wrap">
                                                @if($doc->file_path)
                                                <a href="{{ route('applicant.ntc.document.view', $doc->id) }}"
                                                   target="_blank"
                                                   class="btn btn-xs ntc-doc-btn fw-bold px-2 py-1 d-inline-flex align-items-center gap-1"
                                                   style="font-size: 0.72rem; border-radius: 6px;">
                                                    <i class="far fa-file-pdf text-dark"></i>
                                                    {{ $doc->documentType->code ?? 'DOC' }}
                                                </a>
                                                @else
                                                <span class="text-danger fw-semibold" style="font-size:.75rem;">
                                                    <i class="fas fa-trash-alt me-1"></i>File removed ({{ $doc->documentType->code ?? 'DOC' }})
                                                </span>
                                                @endif
                                                <span class="badge {{ $docBadge[0] }}" style="font-size:.68rem; padding: 4px 8px; border-radius: 12px;">{{ $docBadge[1] }}</span>
                                            </div>
                                            @if($isTrueRejected || $isReturned)
                                                @if($doc->remarks)
                                                <div class="mt-2" style="font-size:.75rem; color:#991b1b; background:#fff5f5; border-radius:6px; padding:6px 10px; border:1px solid #fecaca; line-height: 1.4;">
                                                    <i class="fas fa-comment me-1"></i><strong>Remarks:</strong> {{ $doc->remarks }}
                                                </div>
                                                @endif
                                                @if($isTrueRejected)
                                                <div class="ntc-compact-drop-zone mt-2"
                                                     id="dropZoneReject-{{ $doc->id }}"
                                                     data-input="file-reject-{{ $doc->id }}">
                                                    <div class="file-info text-secondary">
                                                        <i class="fas fa-cloud-upload-alt text-muted fs-6"></i>
                                                        <span>Click or drag file to re-upload...</span>
                                                    </div>
                                                    <button type="button" class="btn-clear d-none no-trigger">Clear</button>
                                                    <input type="file"
                                                           id="file-reject-{{ $doc->id }}"
                                                           name="files[{{ $doc->id }}]"
                                                           class="d-none ntc-file-input"
                                                           accept=".pdf,.doc,.docx"
                                                           >
                                                </div>
                                                @elseif($isReturned)
                                                <div class="mt-2 text-warning d-flex align-items-center gap-1 fw-semibold" style="font-size:.72rem; color: #d97706 !important;">
                                                    <i class="fas fa-hourglass-half spinner-border-sm"></i> Awaiting admin re-evaluation
                                                </div>
                                                @endif
                                            @endif
                                        </div>
                                        @endforeach

                                        @if($rejectedDocsForBatch->isNotEmpty())
                                            <button type="submit"
                                                    class="btn btn-danger btn-sm fw-bold w-100 mt-2 d-inline-flex align-items-center justify-content-center gap-1"
                                                    style="font-size:.75rem; padding: 8px 12px; border-radius:6px; border: none; background: #e11d48; color: #fff;">
                                                <i class="fas fa-cloud-upload-alt"></i> Submit Re-uploaded Documents
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endif

{{-- Report of Changes Modal --}}
<div class="modal fade" id="reportChangesModal" tabindex="-1" aria-labelledby="reportChangesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border-top: 4px solid var(--portal-gold);">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="reportChangesModalLabel" style="color: #2A3F54;">
                    <i class="fas fa-exchange-alt text-warning me-2"></i> Submit Report of Changes
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="reportChangesForm" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning alert-important py-2 mb-3" style="
                        background: #fff8e6;
                        border: 1px solid #f5d98a;
                        border-left: 5px solid #D4AC4B;
                        border-radius: 8px;
                        font-size: 0.87rem;
                        color: #7a5c00;
                    ">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Important Reminder:</strong> Reports of changes must be submitted at least three (3) working days before the first training day using the DOLE-OSHC-STO-RTCMan as per OSHC MC 04 series 2025.
                    </div>

                    <div class="alert alert-info alert-important py-2" style="font-size: 0.85rem;">
                        <i class="fas fa-info-circle me-1"></i>
                        Use this form to update the training details and re-upload files for your acknowledged Notice to Conduct.
                    </div>

                    {{-- Training Type --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="modal_ntc_training_type_id">
                            Type of Training <span class="text-danger">*</span>
                        </label>
                        <select id="modal_ntc_training_type_id"
                                name="ntc_training_type_id"
                                class="form-control"
                                required>
                            <option value="" disabled selected>— Select Training Type —</option>
                            @foreach($trainingTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Mode of Training --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="modal_ntc_training_mode_id">
                            Mode of Training <span class="text-danger">*</span>
                        </label>
                        <select id="modal_ntc_training_mode_id"
                                name="ntc_training_mode_id"
                                class="form-control"
                                required>
                            <option value="" disabled selected>— Select Mode —</option>
                            @foreach($trainingModes as $mode)
                                <option value="{{ $mode->id }}">{{ $mode->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Training Dates --}}
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label class="fw-semibold" for="modal_training_start_date">
                                    Training Start Date <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                       id="modal_training_start_date"
                                       name="training_start_date"
                                       class="form-control"
                                       min="{{ $earliestStartDate }}"
                                       required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label class="fw-semibold" for="modal_training_end_date">
                                    Training End Date <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                       id="modal_training_end_date"
                                       name="training_end_date"
                                       class="form-control"
                                       required>
                            </div>
                        </div>
                    </div>

                    {{-- File: RTCMan Form --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="modal_file_rtcman">
                            DOLE-OSHC-STO-RTCMan Form <span class="text-danger">*</span>
                        </label>
                        <div id="modal_rtcman_current_container" class="mb-2" style="font-size: 0.8rem; display: none;">
                            <span class="text-muted">Current file:</span>
                            <a href="#" id="modal_rtcman_current_link" target="_blank" class="font-monospace text-primary fw-semibold ms-1"></a>
                        </div>
                        <p class="text-muted mb-1" style="font-size:0.8rem;">
                            Accepted formats: <code>.pdf</code>, <code>.doc</code>, <code>.docx</code> &mdash; Max 100 MB
                        </p>
                        <div class="ntc-file-drop-zone"
                             id="modalDropZoneRtcman"
                             data-input="modal_file_rtcman">
                            <div class="ntc-drop-zone-content">
                                <div class="state-empty">
                                    <i class="fas fa-cloud-upload-alt ntc-file-icon"></i>
                                    <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                                    <p class="ntc-file-selected text-muted">No file selected</p>
                                </div>
                                <div class="state-selected d-none">
                                    <i class="fas fa-check-circle text-success fs-4 mb-2"></i>
                                    <p class="selected-file-title fw-bold text-success mb-1">File ready to upload</p>
                                    <p class="selected-file-info mb-2 text-dark font-monospace" style="font-size: 0.78rem;"></p>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-clear-file no-trigger py-1 px-3" style="font-size: 0.72rem; border-radius: 20px;">
                                        <i class="fas fa-trash-alt me-1"></i> Clear Selection
                                    </button>
                                </div>
                            </div>
                            <input type="file"
                                   id="modal_file_rtcman"
                                   name="file_rtcman"
                                   class="d-none ntc-file-input"
                                   accept=".pdf,.doc,.docx">
                        </div>
                        <div class="invalid-feedback-custom text-danger mt-1 d-none" id="error_modal_file_rtcman" style="font-size: 0.85rem;">
                            Please upload the DOLE-OSHC-STO-RTCMan Form.
                        </div>
                    </div>

                    {{-- File: PROG Form --}}
                    <div class="form-group mb-3">
                        <label class="fw-semibold" for="modal_file_prog">
                            DOLE-OSHC-STO-PROG Form <span class="text-danger">*</span>
                        </label>
                        <div id="modal_prog_current_container" class="mb-2" style="font-size: 0.8rem; display: none;">
                            <span class="text-muted">Current file:</span>
                            <a href="#" id="modal_prog_current_link" target="_blank" class="font-monospace text-primary fw-semibold ms-1"></a>
                        </div>
                        <p class="text-muted mb-1" style="font-size:0.8rem;">
                            Accepted formats: <code>.pdf</code>, <code>.doc</code>, <code>.docx</code> &mdash; Max 100 MB
                        </p>
                        <div class="ntc-file-drop-zone"
                             id="modalDropZoneProg"
                             data-input="modal_file_prog">
                            <div class="ntc-drop-zone-content">
                                <div class="state-empty">
                                    <i class="fas fa-cloud-upload-alt ntc-file-icon"></i>
                                    <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                                    <p class="ntc-file-selected text-muted">No file selected</p>
                                </div>
                                <div class="state-selected d-none">
                                    <i class="fas fa-check-circle text-success fs-4 mb-2"></i>
                                    <p class="selected-file-title fw-bold text-success mb-1">File ready to upload</p>
                                    <p class="selected-file-info mb-2 text-dark font-monospace" style="font-size: 0.78rem;"></p>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-clear-file no-trigger py-1 px-3" style="font-size: 0.72rem; border-radius: 20px;">
                                        <i class="fas fa-trash-alt me-1"></i> Clear Selection
                                    </button>
                                </div>
                            </div>
                            <input type="file"
                                   id="modal_file_prog"
                                   name="file_prog"
                                   class="d-none ntc-file-input"
                                   accept=".pdf,.doc,.docx">
                        </div>
                        <div class="invalid-feedback-custom text-danger mt-1 d-none" id="error_modal_file_prog" style="font-size: 0.85rem;">
                            Please upload the DOLE-OSHC-STO-PROG Form.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit"
                            id="modalSubmitBtn"
                            class="btn fw-bold"
                            style="background: linear-gradient(135deg, #D4AC4B, #b8922e); color: #fff; border: none; border-radius: 6px;">
                        <i class="fas fa-paper-plane me-1"></i> Submit Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    /* ── NTC File Drop Zone ─────────────────────────────── */
    .ntc-file-drop-zone {
        border: 2px dashed #b8c8e8;
        border-radius: 10px;
        padding: 24px 20px;
        text-align: center;
        cursor: pointer;
        background: #f7f9fd;
        transition: all 0.2s ease-in-out;
        position: relative;
    }
    .ntc-file-drop-zone:hover,
    .ntc-file-drop-zone.drag-over {
        border-color: var(--portal-gold);
        background: #fffbf0;
    }
    .ntc-file-drop-zone.is-invalid-zone {
        border-color: #dc3545;
        background: #fff8f8;
    }
    .ntc-file-drop-zone.has-file {
        border-color: #27ae60;
        background: #f4fbf7;
    }
    .ntc-file-icon {
        font-size: 2rem;
        color: #b8c8e8;
        display: block;
        margin-bottom: 8px;
        pointer-events: none;
        transition: all 0.2s;
    }
    .ntc-file-drop-zone:hover .ntc-file-icon {
        transform: translateY(-2px);
    }
    .ntc-file-label {
        font-size: 0.92rem;
        color: #475569;
        margin-bottom: 6px;
        pointer-events: none;
    }
    .ntc-browse-link {
        color: #0b3d91;
        font-weight: 600;
        text-decoration: underline;
    }
    .ntc-file-selected {
        font-size: 0.8rem;
        color: #64748b;
        margin: 0;
        pointer-events: none;
        word-break: break-all;
    }

    /* ── Compact Drop Zone (for table row re-uploads) ───── */
    .ntc-compact-drop-zone {
        border: 2px dashed #cbd5e1;
        border-radius: 8px;
        padding: 10px 14px;
        background: #f8fafc;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.2s;
    }
    .ntc-compact-drop-zone.is-invalid-zone {
        border-color: #dc3545;
        background: #fff8f8;
    }
    .ntc-compact-drop-zone:hover,
    .ntc-compact-drop-zone.drag-over {
        border-color: #e11d48;
        background: #fff5f5;
    }
    .ntc-compact-drop-zone .file-info {
        font-size: 0.78rem;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 6px;
        max-width: 80%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        pointer-events: none;
    }
    .ntc-compact-drop-zone .btn-clear {
        padding: 0;
        border: none;
        background: none;
        color: #e11d48;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
    }
    .ntc-compact-drop-zone .btn-clear:hover {
        text-decoration: underline;
    }

    /* ── Spin loading on submit ─────────────────────────── */
    #ntcSubmitBtn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    /* ── Premium NTC Table Styles ────────────────────────── */
    .ntc-premium-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        background: #fff;
        overflow: hidden;
        border-top: 3px solid var(--portal-gold);
    }
    .ntc-table th {
        font-weight: 700;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #eef2f6;
    }
    .ntc-table tbody tr {
        transition: all 0.2s ease-in-out;
    }
    .ntc-table tbody tr:hover {
        background-color: #f8fafc !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    }
    .ntc-ref-link {
        font-size: 0.92rem;
        color: #0b3d91;
        font-weight: 700;
        letter-spacing: -0.2px;
    }
    .ntc-date-container {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        color: #475569;
    }
    .ntc-date-badge {
        background: #f1f5f9;
        border-radius: 6px;
        padding: 4px 8px;
        font-weight: 600;
        border: 1px solid #e2e8f0;
    }
    .badge-premium-success {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 20px;
    }
    .badge-premium-danger {
        background: #fff1f2;
        color: #be123c;
        border: 1px solid #fecdd3;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 20px;
    }
    .badge-premium-warning {
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fde68a;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 20px;
    }
    .badge-premium-secondary {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 20px;
    }
    .ntc-doc-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        margin-bottom: 8px;
        transition: all 0.2s;
    }
    .ntc-doc-item:hover {
        border-color: #cbd5e1;
        background: #f1f5f9;
    }
    .ntc-doc-btn {
        background: #fff;
        border: 1px solid #cbd5e1;
        color: #0b3d91;
        transition: all 0.2s;
    }
    .ntc-doc-btn:hover {
        background: #0b3d91;
        color: #fff;
        border-color: #0b3d91;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Drop Zone Controller Helper ──────────────────────────
    function setupFileDropZone(zoneId) {
        const zone = document.getElementById(zoneId);
        if (!zone) return null;

        const input = zone.querySelector('.ntc-file-input');
        const stateEmpty = zone.querySelector('.state-empty');
        const stateSelected = zone.querySelector('.state-selected');
        const stateExisting = zone.querySelector('.state-existing');
        const stateReplacement = zone.querySelector('.state-replacement');

        const selectedInfo = zone.querySelector('.selected-file-info');
        const existingName = zone.querySelector('.existing-file-name');
        const btnView = zone.querySelector('.btn-view-file');
        const replacementInfo = zone.querySelector('.replacement-file-info');

        const btnClear = zone.querySelector('.btn-clear-file');
        const btnUndo = zone.querySelector('.btn-undo-replacement');

        let currentFileState = {
            hasExisting: false,
            existingName: '',
            existingUrl: '',
            newFile: null
        };

        const MAX_MB = 100;
        const MAX_BYTES = MAX_MB * 1024 * 1024;
        const validExts = ['.pdf', '.doc', '.docx'];

        function formatBytes(bytes) {
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function updateUIState() {
            // Hide all states first
            stateEmpty.classList.add('d-none');
            if (stateSelected) stateSelected.classList.add('d-none');
            if (stateExisting) stateExisting.classList.add('d-none');
            if (stateReplacement) stateReplacement.classList.add('d-none');

            // Hide validation error if file is present
            const errorEl = document.getElementById('error_' + input.id);
            if (currentFileState.newFile) {
                if (errorEl) errorEl.classList.add('d-none');
                zone.classList.remove('is-invalid-zone');
            }

            if (currentFileState.newFile) {
                // New file selected
                if (currentFileState.hasExisting) {
                    if (stateReplacement) {
                        stateReplacement.classList.remove('d-none');
                        replacementInfo.textContent = currentFileState.newFile.name + ' (' + formatBytes(currentFileState.newFile.size) + ')';
                    }
                } else {
                    if (stateSelected) {
                        stateSelected.classList.remove('d-none');
                        selectedInfo.textContent = currentFileState.newFile.name + ' (' + formatBytes(currentFileState.newFile.size) + ')';
                    }
                }
                zone.classList.add('has-file');
            } else if (currentFileState.hasExisting) {
                // Existing file
                if (stateExisting) {
                    stateExisting.classList.remove('d-none');
                    existingName.textContent = currentFileState.existingName;
                    if (btnView && currentFileState.existingUrl) {
                        btnView.href = currentFileState.existingUrl;
                    }
                }
                zone.classList.remove('has-file');
            } else {
                // Empty state
                stateEmpty.classList.remove('d-none');
                zone.classList.remove('has-file');
            }
        }

        function validateFile(file) {
            if (!file) return false;
            const ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
            if (!validExts.includes(ext)) {
                alert('Invalid file type. Please upload a PDF, DOC, or DOCX file.');
                return false;
            }
            if (file.size > MAX_BYTES) {
                alert('File is too large. Maximum size allowed is 100 MB.');
                return false;
            }
            return true;
        }

        function selectFile(file) {
            if (validateFile(file)) {
                currentFileState.newFile = file;
                updateUIState();
            } else {
                clearSelection();
            }
        }

        function clearSelection() {
            input.value = '';
            currentFileState.newFile = null;
            updateUIState();
        }

        function setExistingFile(name, url) {
            if (name && url) {
                currentFileState.hasExisting = true;
                currentFileState.existingName = name;
                currentFileState.existingUrl = url;
            } else {
                currentFileState.hasExisting = false;
                currentFileState.existingName = '';
                currentFileState.existingUrl = '';
            }
            clearSelection();
        }

        zone.addEventListener('click', function(e) {
            if (e.target.closest('.no-trigger')) {
                return;
            }
            input.click();
        });

        input.addEventListener('change', function() {
            if (input.files && input.files.length > 0) {
                selectFile(input.files[0]);
            }
        });

        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function() {
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                const file = e.dataTransfer.files[0];
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                input.files = dataTransfer.files;
                selectFile(file);
            }
        });

        if (btnClear) {
            btnClear.addEventListener('click', function(e) {
                e.stopPropagation();
                clearSelection();
            });
        }

        if (btnUndo) {
            btnUndo.addEventListener('click', function(e) {
                e.stopPropagation();
                clearSelection();
            });
        }

        // Initial UI update
        updateUIState();

        return {
            clear: clearSelection,
            setExisting: setExistingFile,
            getCurrentState: () => currentFileState
        };
    }

    // ── Setup Compact Drop Zones (for individual rejected docs) ───────
    function setupCompactDropZone(zoneId) {
        const zone = document.getElementById(zoneId);
        if (!zone) return;

        const input = zone.querySelector('.ntc-file-input');
        const fileInfo = zone.querySelector('.file-info');
        const btnClear = zone.querySelector('.btn-clear');

        const defaultHTML = fileInfo.innerHTML;
        const MAX_MB = 100;
        const MAX_BYTES = MAX_MB * 1024 * 1024;
        const validExts = ['.pdf', '.doc', '.docx'];

        function selectFile(file) {
            const ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
            if (!validExts.includes(ext)) {
                alert('Invalid file type. Please upload a PDF, DOC, or DOCX file.');
                input.value = '';
                return;
            }
            if (file.size > MAX_BYTES) {
                alert('File is too large. Maximum size allowed is 100 MB.');
                input.value = '';
                return;
            }

            fileInfo.innerHTML = `<i class="fas fa-check-circle text-success fs-6"></i> <span class="text-success fw-bold">${file.name}</span>`;
            btnClear.classList.remove('d-none');
            zone.classList.remove('is-invalid-zone');
        }

        function clearSelection() {
            input.value = '';
            fileInfo.innerHTML = defaultHTML;
            btnClear.classList.add('d-none');
            zone.classList.remove('is-invalid-zone');
        }

        zone.addEventListener('click', function(e) {
            if (e.target.closest('.no-trigger')) {
                return;
            }
            input.click();
        });

        input.addEventListener('change', function() {
            if (input.files && input.files.length > 0) {
                selectFile(input.files[0]);
            }
        });

        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function() {
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                const file = e.dataTransfer.files[0];
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                input.files = dataTransfer.files;
                selectFile(file);
            }
        });

        if (btnClear) {
            btnClear.addEventListener('click', function(e) {
                e.stopPropagation();
                clearSelection();
            });
        }
    }

    // Initialize Submit Form drop zones
    const mainRtcmanCtrl = setupFileDropZone('dropZoneRtcman');
    const mainProgCtrl = setupFileDropZone('dropZoneProg');

    // Initialize Modal drop zones
    const modalRtcmanCtrl = setupFileDropZone('modalDropZoneRtcman');
    const modalProgCtrl = setupFileDropZone('modalDropZoneProg');

    // Initialize all existing compact drop zones
    document.querySelectorAll('.ntc-compact-drop-zone').forEach(zone => {
        setupCompactDropZone(zone.id);
    });

    // ── Training End Date: enforce >= Start Date ──────────
    const startInput = document.getElementById('training_start_date');
    const endInput   = document.getElementById('training_end_date');

    if (startInput && endInput) {
        const updateMinEndDate = () => {
            endInput.min = startInput.value;
            if (endInput.value && endInput.value < startInput.value) {
                endInput.value = startInput.value;
            }
        };
        startInput.addEventListener('change', updateMinEndDate);
        if (startInput.value) {
            updateMinEndDate();
        }
    }

    // ── Submit spinner guard ──────────────────────────────
    const form       = document.getElementById('ntcSubmitForm');
    const submitBtn  = document.getElementById('ntcSubmitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {
            let isValid = true;

            // Check RTCMan file
            const fileRtcman = document.getElementById('file_rtcman');
            const errorRtcman = document.getElementById('error_file_rtcman');
            const zoneRtcman = document.getElementById('dropZoneRtcman');
            if (fileRtcman && (!fileRtcman.files || fileRtcman.files.length === 0)) {
                if (errorRtcman) errorRtcman.classList.remove('d-none');
                if (zoneRtcman) zoneRtcman.classList.add('is-invalid-zone');
                isValid = false;
            }

            // Check PROG file
            const fileProg = document.getElementById('file_prog');
            const errorProg = document.getElementById('error_file_prog');
            const zoneProg = document.getElementById('dropZoneProg');
            if (fileProg && (!fileProg.files || fileProg.files.length === 0)) {
                if (errorProg) errorProg.classList.remove('d-none');
                if (zoneProg) zoneProg.classList.add('is-invalid-zone');
                isValid = false;
            }

            if (!form.checkValidity() || !isValid) {
                e.preventDefault();
                form.reportValidity();
                return;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
        });
    }

    // ── Report of Changes Modal Populating ────────────────
    const reportChangesModalEl = document.getElementById('reportChangesModal');
    const reportChangesModal = reportChangesModalEl ? new bootstrap.Modal(reportChangesModalEl) : null;
    const reportChangesForm = document.getElementById('reportChangesForm');

    if (reportChangesModalEl && reportChangesModal) {
        reportChangesModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', function () {
                reportChangesModal.hide();
            });
        });
    }

    document.querySelectorAll('.btn-report-changes').forEach(button => {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const trainingType = this.getAttribute('data-training-type');
            const trainingMode = this.getAttribute('data-training-mode');
            const startDate = this.getAttribute('data-start-date');
            const endDate = this.getAttribute('data-end-date');

            const rtcmanName = this.getAttribute('data-rtcman-file-name');
            const rtcmanUrl = this.getAttribute('data-rtcman-file-url');
            const progName = this.getAttribute('data-prog-file-name');
            const progUrl = this.getAttribute('data-prog-file-url');

            if (reportChangesForm) {
                reportChangesForm.action = `/applicant/ntc/${id}/report-of-changes`;

                const typeSelect = document.getElementById('modal_ntc_training_type_id');
                if (typeSelect) {
                    typeSelect.value = trainingType;
                }

                const modeSelect = document.getElementById('modal_ntc_training_mode_id');
                if (modeSelect) {
                    modeSelect.value = trainingMode;
                }
                
                const startInputModal = document.getElementById('modal_training_start_date');
                const endInputModal = document.getElementById('modal_training_end_date');
                
                if (startInputModal) {
                    startInputModal.value = startDate;
                }
                if (endInputModal) {
                    endInputModal.value = endDate;
                    if (startInputModal) {
                        endInputModal.min = startInputModal.value;
                    }
                }
            }

            // Update Current File Links
            const rtcmanCurrentContainer = document.getElementById('modal_rtcman_current_container');
            const rtcmanCurrentLink = document.getElementById('modal_rtcman_current_link');
            if (rtcmanCurrentContainer && rtcmanCurrentLink) {
                if (rtcmanName && rtcmanUrl) {
                    rtcmanCurrentLink.textContent = rtcmanName;
                    rtcmanCurrentLink.href = rtcmanUrl;
                    rtcmanCurrentContainer.style.display = 'block';
                } else {
                    rtcmanCurrentContainer.style.display = 'none';
                }
            }

            const progCurrentContainer = document.getElementById('modal_prog_current_container');
            const progCurrentLink = document.getElementById('modal_prog_current_link');
            if (progCurrentContainer && progCurrentLink) {
                if (progName && progUrl) {
                    progCurrentLink.textContent = progName;
                    progCurrentLink.href = progUrl;
                    progCurrentContainer.style.display = 'block';
                } else {
                    progCurrentContainer.style.display = 'none';
                }
            }

            if (modalRtcmanCtrl) {
                modalRtcmanCtrl.clear();
            }
            if (modalProgCtrl) {
                modalProgCtrl.clear();
            }

            if (reportChangesModal) {
                reportChangesModal.show();
            }
        });
    });

    // ── Modal Training End Date: enforce >= Start Date ──────────
    const modalStartInput = document.getElementById('modal_training_start_date');
    const modalEndInput   = document.getElementById('modal_training_end_date');

    if (modalStartInput && modalEndInput) {
        const updateModalMinEndDate = () => {
            modalEndInput.min = modalStartInput.value;
            if (modalEndInput.value && modalEndInput.value < modalStartInput.value) {
                modalEndInput.value = modalStartInput.value;
            }
        };
        modalStartInput.addEventListener('change', updateModalMinEndDate);
        if (modalStartInput.value) {
            updateModalMinEndDate();
        }
    }

    // ── Modal Submit Spinner Guard ────────────────────────
    const modalSubmitBtn = document.getElementById('modalSubmitBtn');
    if (reportChangesForm && modalSubmitBtn) {
        reportChangesForm.addEventListener('submit', function (e) {
            let isValid = true;

            // Check RTCMan file in modal
            const fileRtcman = document.getElementById('modal_file_rtcman');
            const errorRtcman = document.getElementById('error_modal_file_rtcman');
            const zoneRtcman = document.getElementById('modalDropZoneRtcman');
            if (fileRtcman && (!fileRtcman.files || fileRtcman.files.length === 0)) {
                if (errorRtcman) errorRtcman.classList.remove('d-none');
                if (zoneRtcman) zoneRtcman.classList.add('is-invalid-zone');
                isValid = false;
            }

            // Check PROG file in modal
            const fileProg = document.getElementById('modal_file_prog');
            const errorProg = document.getElementById('error_modal_file_prog');
            const zoneProg = document.getElementById('modalDropZoneProg');
            if (fileProg && (!fileProg.files || fileProg.files.length === 0)) {
                if (errorProg) errorProg.classList.remove('d-none');
                if (zoneProg) zoneProg.classList.add('is-invalid-zone');
                isValid = false;
            }

            if (!reportChangesForm.checkValidity() || !isValid) {
                e.preventDefault();
                reportChangesForm.reportValidity();
                return;
            }
            modalSubmitBtn.disabled = true;
            modalSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
        });
    }

    // ── Re-upload Batch Submit Spinner Guard ───────────────────
    document.querySelectorAll('.ntc-reupload-form').forEach(reuploadForm => {
        reuploadForm.addEventListener('submit', function (e) {
            let isValid = true;
            
            // Check all file inputs in this form
            reuploadForm.querySelectorAll('.ntc-file-input').forEach(input => {
                const zone = input.closest('.ntc-compact-drop-zone');
                if (!input.files || input.files.length === 0) {
                    if (zone) {
                        zone.classList.add('is-invalid-zone');
                    }
                    isValid = false;
                }
            });

            if (!reuploadForm.checkValidity() || !isValid) {
                e.preventDefault();
                reuploadForm.reportValidity();
                return;
            }
            const reuploadSubmitBtn = reuploadForm.querySelector('button[type="submit"]');
            if (reuploadSubmitBtn) {
                reuploadSubmitBtn.disabled = true;
                reuploadSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
            }
        });
    });

});
</script>
@endpush
