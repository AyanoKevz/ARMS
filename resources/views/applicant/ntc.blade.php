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
                            <i class="fas fa-file-upload ntc-file-icon"></i>
                            <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                            <p class="ntc-file-selected" id="rtcmanFileName">No file selected</p>
                            <input type="file"
                                   id="file_rtcman"
                                   name="file_rtcman"
                                   class="ntc-file-hidden"
                                   accept=".pdf,.doc,.docx"
                                   required>
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
                            <i class="fas fa-file-upload ntc-file-icon"></i>
                            <p class="ntc-file-label">Drag & drop or <span class="ntc-browse-link">browse</span></p>
                            <p class="ntc-file-selected" id="progFileName">No file selected</p>
                            <input type="file"
                                   id="file_prog"
                                   name="file_prog"
                                   class="ntc-file-hidden"
                                   accept=".pdf,.doc,.docx"
                                   required>
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
                                    <td style="white-space: nowrap;">
                                        <div class="ntc-date-container">
                                            <i class="far fa-calendar-alt text-muted"></i>
                                            <span class="ntc-date-badge">{{ $ntc->training_start_date ? $ntc->training_start_date->format('M d, Y') : 'N/A' }}</span>
                                            <span class="text-muted" style="font-size: 0.75rem;">to</span>
                                            <span class="ntc-date-badge">{{ $ntc->training_end_date ? $ntc->training_end_date->format('M d, Y') : 'N/A' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if($ntc->status === 'acknowledged')
                                            <span class="badge badge-premium-success">Acknowledged</span>
                                        @elseif($hasRejected)
                                            <span class="badge badge-premium-danger">Requires Re-submission</span>
                                        @elseif($ntc->status === 'submitted')
                                            <span class="badge badge-premium-warning">Submitted</span>
                                        @else
                                            <span class="badge badge-premium-secondary">{{ ucfirst($ntc->status) }}</span>
                                        @endif
                                        @if($ntc->submitted_at)
                                            <div class="text-muted mt-1" style="font-size:0.7rem;">
                                                Sub: {{ $ntc->submitted_at->format('M d, Y') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="pe-4">
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
                                                    <i class="far fa-file-pdf text-danger"></i>
                                                    {{ $doc->documentType->code ?? 'DOC' }}
                                                </a>
                                                @else
                                                <span class="text-danger fw-semibold" style="font-size:.75rem;">
                                                    <i class="fas fa-trash-alt me-1"></i>File removed
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
                                                <form method="POST"
                                                      action="{{ route('applicant.ntc.document.reupload', $doc->id) }}"
                                                      enctype="multipart/form-data"
                                                      class="mt-2">
                                                    @csrf
                                                    <div class="d-flex gap-2 align-items-center flex-wrap">
                                                        <input type="file"
                                                               name="file"
                                                               accept=".pdf,.doc,.docx"
                                                               required
                                                               style="font-size:.75rem; max-width:180px; border-radius: 6px;"
                                                               class="form-control form-control-sm">
                                                        <button type="submit"
                                                                class="btn btn-danger btn-sm fw-semibold d-inline-flex align-items-center gap-1"
                                                                style="font-size:.72rem; padding: 4px 12px; border-radius:6px; white-space:nowrap; border: none; background: #e11d48;">
                                                            <i class="fas fa-cloud-upload-alt"></i> Re-upload
                                                        </button>
                                                    </div>
                                                </form>
                                                @elseif($isReturned)
                                                <div class="mt-2 text-warning d-flex align-items-center gap-1 fw-semibold" style="font-size:.72rem; color: #d97706 !important;">
                                                    <i class="fas fa-hourglass-half spinner-border-sm"></i> Awaiting admin re-evaluation
                                                </div>
                                                @endif
                                            @endif
                                        </div>
                                        @endforeach
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

@endsection

@push('styles')
<style>
    /* ── NTC File Drop Zone ─────────────────────────────── */
    .ntc-file-drop-zone {
        border: 2px dashed #b8c8e8;
        border-radius: 10px;
        padding: 20px 16px;
        text-align: center;
        cursor: pointer;
        background: #f7f9fd;
        transition: border-color 0.2s, background 0.2s;
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
    .ntc-file-hidden {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    .ntc-file-icon {
        font-size: 1.8rem;
        color: #b8c8e8;
        display: block;
        margin-bottom: 6px;
        pointer-events: none;
    }
    .ntc-file-label {
        font-size: 0.88rem;
        color: #666;
        margin-bottom: 4px;
        pointer-events: none;
    }
    .ntc-browse-link {
        color: #0b3d91;
        font-weight: 600;
        text-decoration: underline;
    }
    .ntc-file-selected {
        font-size: 0.78rem;
        color: #888;
        margin: 0;
        pointer-events: none;
        word-break: break-all;
    }
    .ntc-file-selected.has-file {
        color: #27ae60;
        font-weight: 600;
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

    // ── File Drop Zone Behaviour ──────────────────────────
    const dropZones = document.querySelectorAll('.ntc-file-drop-zone');

    dropZones.forEach(function (zone) {
        const inputId  = zone.getAttribute('data-input');
        const input    = document.getElementById(inputId);
        const label    = zone.querySelector('.ntc-file-selected');
        const MAX_MB   = 100;
        const MAX_BYTES = MAX_MB * 1024 * 1024;

        const validMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        const validExts = ['.pdf', '.doc', '.docx'];

        function formatBytes(bytes) {
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function applyFile(file) {
            if (!file) return;

            const ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
            if (!validExts.includes(ext)) {
                label.textContent = '❌ Invalid file type. Use PDF, DOC, or DOCX.';
                label.classList.remove('has-file');
                input.value = '';
                return;
            }

            if (file.size > MAX_BYTES) {
                label.textContent = '❌ File too large. Max 100 MB allowed.';
                label.classList.remove('has-file');
                input.value = '';
                return;
            }

            label.textContent = '✓ ' + file.name + ' (' + formatBytes(file.size) + ')';
            label.classList.add('has-file');
        }

        // Click to pick a file
        zone.addEventListener('click', function (e) {
            if (e.target !== input) input.click();
        });

        // File chosen via picker
        input.addEventListener('change', function () {
            applyFile(input.files[0]);
        });

        // Drag & drop
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        });
        zone.addEventListener('dragleave', function () {
            zone.classList.remove('drag-over');
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('drag-over');
            const dt = e.dataTransfer;
            if (dt && dt.files.length > 0) {
                // Transfer the file to the hidden input
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(dt.files[0]);
                input.files = dataTransfer.files;
                applyFile(dt.files[0]);
            }
        });
    });

    // ── Training End Date: enforce >= Start Date ──────────
    const startInput = document.getElementById('training_start_date');
    const endInput   = document.getElementById('training_end_date');

    if (startInput && endInput) {
        startInput.addEventListener('change', function () {
            endInput.min = startInput.value;
            if (endInput.value && endInput.value < startInput.value) {
                endInput.value = startInput.value;
            }
        });
    }

    // ── Submit spinner guard ──────────────────────────────
    const form       = document.getElementById('ntcSubmitForm');
    const submitBtn  = document.getElementById('ntcSubmitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {
            // Basic client-side guard
            if (!form.checkValidity()) {
                e.preventDefault();
                form.reportValidity();
                return;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
        });
    }

});
</script>
@endpush
